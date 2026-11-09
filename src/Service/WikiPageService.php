<?php

declare(strict_types=1);

namespace Citadel\Wiki\Service;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiLink;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Entity\WikiRevision;
use Citadel\Wiki\Exception\EditConflictException;
use Citadel\Wiki\Markdown\RenderedMarkdown;
use Citadel\Wiki\Markdown\WikiMarkdownRenderer;
use Citadel\Wiki\Repository\WikiLinkRepository;
use Citadel\Wiki\Repository\WikiPageRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Throwable;

class WikiPageService
{
    public const MAX_CONTENT_BYTES = 200000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WikiMarkdownRenderer $renderer,
        private readonly WikiPageRepository $pageRepository,
        private readonly WikiLinkRepository $linkRepository,
    ) {
    }

    /**
     * @throws EditConflictException when the page changed after $baseRevisionId, or was created concurrently
     */
    public function save(
        WikiPage $page,
        string $title,
        string $content,
        ?User $author,
        ?string $summary = null,
        bool $minor = false,
        ?int $baseRevisionId = null,
        bool $checkConflict = true,
    ): ?WikiRevision {
        $content = $this->normalize($content);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $revision = $this->persistEdit($page, $title, $content, $author, $summary, $minor, $baseRevisionId, $checkConflict);
            $connection->commit();

            return $revision;
        } catch (UniqueConstraintViolationException $e) {
            $connection->rollBack();

            throw new EditConflictException('The page was created by someone else.', previous: $e);
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    public function setProtected(WikiPage $page, bool $protected): void
    {
        $page->setProtected($protected);
        $this->entityManager->flush();
    }

    /**
     * Reloads a page after a rejected save. Returns false when the failed save closed the entity manager.
     */
    public function reloadAfterConflict(WikiPage $page): bool
    {
        if (!$this->entityManager->isOpen()) {
            return false;
        }

        $this->entityManager->refresh($page);

        return true;
    }

    private function persistEdit(
        WikiPage $page,
        string $title,
        string $content,
        ?User $author,
        ?string $summary,
        bool $minor,
        ?int $baseRevisionId,
        bool $checkConflict,
    ): ?WikiRevision {
        $isNew = !$this->entityManager->contains($page);
        if (!$isNew && $checkConflict) {
            $this->assertNotStale($page, $baseRevisionId);
        }

        $page->setTitle($title);
        if (!$isNew && $content === $page->getContent()) {
            $rendered = $this->renderer->render($page->getWiki(), $content);
            $this->applyRendered($page, $rendered);
            $this->replaceLinks($page, $rendered->links);

            return null;
        }

        $size = strlen($content);
        $revision = new WikiRevision(
            $page,
            $author,
            $content,
            $summary === '' ? null : $summary,
            $minor,
            $size,
            $size - strlen($page->getContent()),
        );

        $rendered = $this->renderer->render($page->getWiki(), $content);
        $this->applyRendered($page, $rendered);
        $page->setContent($content);

        $this->entityManager->persist($page);
        $this->entityManager->persist($revision);
        $this->entityManager->flush();

        $page->setCurrentRevision($revision);
        $this->replaceLinks($page, $rendered->links);
        $this->entityManager->flush();

        if ($isNew) {
            $this->rerenderLinkersOf($page->getWiki(), $page->getSlug());
        }

        return $revision;
    }

    public function restore(WikiRevision $revision, ?User $author, string $summary): ?WikiRevision
    {
        $page = $revision->getPage();

        return $this->save($page, $page->getTitle(), $revision->getContent(), $author, $summary, checkConflict: false);
    }

    public function delete(WikiPage $page): void
    {
        $wiki = $page->getWiki();
        $slug = $page->getSlug();

        $this->entityManager->wrapInTransaction(function () use ($page, $wiki, $slug): void {
            $page->setCurrentRevision(null);
            $this->entityManager->flush();
            foreach ([WikiRevision::class => 'page', WikiLink::class => 'fromPage'] as $class => $field) {
                foreach ($this->entityManager->getRepository($class)->findBy([$field => $page]) as $child) {
                    $this->entityManager->remove($child);
                }
            }
            $this->entityManager->remove($page);
            $this->entityManager->flush();
            $this->rerenderLinkersOf($wiki, $slug);
        });
    }

    private function assertNotStale(WikiPage $page, ?int $baseRevisionId): void
    {
        $current = $this->entityManager->getConnection()->fetchOne(
            'SELECT current_revision_id FROM wiki_page WHERE id = ? FOR UPDATE',
            [$page->getId()],
        );

        if ($baseRevisionId === null || (int)$current !== $baseRevisionId) {
            throw new EditConflictException('The page changed since editing started.');
        }
    }

    private function normalize(string $content): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $content)) . "\n";
    }

    /**
     * @param array<string> $slugs
     */
    private function replaceLinks(WikiPage $page, array $slugs): void
    {
        $this->linkRepository->deleteForPage($page);
        foreach ($slugs as $slug) {
            $this->entityManager->persist(new WikiLink($page, $slug));
        }

        $this->entityManager->flush();
    }

    private function rerenderLinkersOf(Wiki $wiki, string $slug): void
    {
        foreach ($this->pageRepository->findLinkingTo($wiki, $slug) as $linker) {
            $this->applyRendered($linker, $this->renderer->render($wiki, $linker->getContent()));
        }

        $this->entityManager->flush();
    }

    private function applyRendered(WikiPage $page, RenderedMarkdown $rendered): void
    {
        $page->setHtml($rendered->html);
        $page->setToc($rendered->toc);
    }
}
