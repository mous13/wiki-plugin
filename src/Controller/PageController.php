<?php

declare(strict_types=1);

namespace Citadel\Wiki\Controller;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Entity\WikiRevision;
use Citadel\Wiki\Exception\EditConflictException;
use Citadel\Wiki\Form\DTO\WikiPageData;
use Citadel\Wiki\Form\WikiPageType;
use Citadel\Wiki\Repository\WikiLinkRepository;
use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Repository\WikiRevisionRepository;
use Citadel\Wiki\Service\ArticleComposerService;
use Citadel\Wiki\Service\ContentExpanderService;
use Citadel\Wiki\Service\DiffService;
use Citadel\Wiki\Service\PageSlugger;
use Citadel\Wiki\Service\WikiPageService;
use Forumify\Core\Entity\User;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{slug:wiki}/{page}', requirements: ['page' => PageSlugger::ROUTE_REQUIREMENT])]
class PageController extends AbstractWikiController
{
    public function __construct(
        private readonly WikiPageRepository $pageRepository,
        private readonly WikiRevisionRepository $revisionRepository,
        private readonly WikiLinkRepository $linkRepository,
        private readonly WikiPageService $pageService,
        private readonly DiffService $diffService,
        private readonly ArticleComposerService $articleComposerService,
        private readonly ContentExpanderService $contentExpanderService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', 'page')]
    public function view(Wiki $wiki, string $page): Response
    {
        $this->assertCanView($wiki);
        $entity = $this->pageRepository->findBySlug($wiki, $page);
        $isCategory = PageSlugger::isCategory($page);
        $members = $isCategory ? $this->pageRepository->findLinkingTo($wiki, $page) : [];
        $subcategories = array_values(array_filter($members, static fn (WikiPage $member): bool => PageSlugger::isCategory($member->getSlug())));

        $context = [
            'wiki' => $wiki,
            'slug' => $page,
            'page' => $entity,
            'title' => PageSlugger::displayTitle($entity?->getTitle() ?? PageSlugger::humanize($page)),
            'canEdit' => $this->accessService->canEdit($wiki, $entity),
            'canModerate' => $this->accessService->canModerate($wiki),
            'members' => array_values(array_filter($members, static fn (WikiPage $member): bool => !PageSlugger::isCategory($member->getSlug()))),
            'subcategories' => array_map(static fn (WikiPage $member): array => [
                'slug' => $member->getSlug(),
                'label' => PageSlugger::categoryLabel($member->getSlug()),
            ], $subcategories),
            'isCategory' => $isCategory,
        ];

        if ($entity === null) {
            $status = $isCategory && $members !== [] ? Response::HTTP_OK : Response::HTTP_NOT_FOUND;

            return $this->render('@CitadelWikiPlugin/frontend/page_missing.html.twig', $context, new Response(status: $status));
        }

        $categories = array_map(static fn (string $slug): array => [
            'slug' => $slug,
            'label' => PageSlugger::categoryLabel($slug),
        ], $this->linkRepository->findCategorySlugs($entity));

        return $this->render('@CitadelWikiPlugin/frontend/page.html.twig', $context + [
            'categories' => $categories,
            'article' => $this->articleComposerService->compose($this->contentExpanderService->expand($entity->getHtml()), $entity->getToc()),
            'revisionCount' => $this->revisionRepository->countForPage($entity),
            'breadcrumbs' => $this->breadcrumbs($entity),
        ]);
    }

    #[Route('/edit', 'page_edit')]
    public function edit(Wiki $wiki, string $page, Request $request): Response
    {
        $this->assertCanView($wiki);
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (strlen($page) > strlen(PageSlugger::CATEGORY_PREFIX) + PageSlugger::MAX_LENGTH) {
            throw $this->createNotFoundException();
        }

        $entity = $this->pageRepository->findBySlug($wiki, $page);
        if (!$this->accessService->canEdit($wiki, $entity)) {
            throw $this->createAccessDeniedException();
        }

        $newTitle = trim($request->query->getString('title', PageSlugger::humanize($page)));
        $data = WikiPageData::fromPage($entity, $newTitle);
        $form = $this->createForm(WikiPageType::class, $data);
        $form->handleRequest($request);
        $conflictDiff = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $parent = $this->findParent($wiki, $data->parent);
            $parentError = $this->parentError($entity, $data->parent, $parent);
            if ($parentError !== null) {
                $form->get('parent')->addError(new FormError($this->translator->trans($parentError)));
            } else {
                $target = $entity ?? new WikiPage($wiki, $page);
                $target->setParent($parent);
                try {
                    $this->pageService->save($target, trim($data->title), $data->content, $this->currentUser(), trim((string)$data->summary), $data->minor, $data->getBaseRevisionId());
                    $this->addFlash('success', 'wiki.page.saved');

                    return $this->redirectToRoute('wiki_page', ['slug' => $wiki->getSlug(), 'page' => $page]);
                } catch (EditConflictException) {
                    if ($entity === null || !$this->pageService->reloadAfterConflict($entity)) {
                        $this->addFlash('error', 'wiki.page.created_concurrently');

                        return $this->redirectToRoute('wiki_page', ['slug' => $wiki->getSlug(), 'page' => $page]);
                    }

                    $baseId = $data->getBaseRevisionId();
                    $base = $baseId !== null ? $this->revisionRepository->findOnPage($entity, $baseId) : null;
                    $conflictDiff = $this->diffService->build($base?->getContent() ?? '', $entity->getContent());
                    $data->base = WikiPageData::baseOf($entity);
                    $form = $this->createForm(WikiPageType::class, $data);
                }
            }
        }

        return $this->render('@CitadelWikiPlugin/frontend/edit.html.twig', [
            'wiki' => $wiki,
            'slug' => $page,
            'page' => $entity,
            'title' => PageSlugger::displayTitle($entity?->getTitle() ?? $data->title),
            'form' => $form,
            'baseRevisionId' => $entity?->getCurrentRevision()?->getId(),
            'conflictDiff' => $conflictDiff,
            'canEdit' => true,
        ], new Response(status: $conflictDiff !== null ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/history', 'page_history')]
    public function history(Wiki $wiki, string $page, Request $request): Response
    {
        $entity = $this->viewablePage($wiki, $page);

        $a = $request->query->getInt('a');
        $b = $request->query->getInt('b');
        if ($a > 0 && $b > 0) {
            return $this->redirectToRoute('wiki_page_diff', [
                'slug' => $wiki->getSlug(),
                'page' => $page,
                'a' => min($a, $b),
                'b' => max($a, $b),
            ]);
        }

        $number = $this->pageNumber($request);

        return $this->render('@CitadelWikiPlugin/frontend/history.html.twig', [
            'wiki' => $wiki,
            'slug' => $page,
            'page' => $entity,
            'title' => PageSlugger::displayTitle($entity->getTitle()),
            'pager' => $this->paginate(
                $this->revisionRepository->findForPage($entity, self::PER_PAGE + 1, $this->offset($number)),
                $number,
            ),
            'canEdit' => $this->accessService->canEdit($wiki, $entity),
        ]);
    }

    #[Route('/diff/{a}/{b}', 'page_diff', requirements: ['a' => '\d+', 'b' => '\d+'])]
    public function diff(Wiki $wiki, string $page, int $a, int $b): Response
    {
        $entity = $this->viewablePage($wiki, $page);
        $from = $this->revisionOr404($entity, $a);
        $to = $this->revisionOr404($entity, $b);

        return $this->render('@CitadelWikiPlugin/frontend/diff.html.twig', [
            'wiki' => $wiki,
            'slug' => $page,
            'page' => $entity,
            'title' => PageSlugger::displayTitle($entity->getTitle()),
            'from' => $from,
            'to' => $to,
            'rows' => $this->diffService->build($from->getContent(), $to->getContent()),
            'canEdit' => $this->accessService->canEdit($wiki, $entity),
        ]);
    }

    #[Route('/restore/{revision}', 'page_restore', requirements: ['revision' => '\d+'], methods: ['POST'])]
    public function restore(Wiki $wiki, string $page, int $revision, Request $request): Response
    {
        $entity = $this->viewablePage($wiki, $page);
        $this->assertToken($request, 'wiki-restore');
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->accessService->canEdit($wiki, $entity)) {
            throw $this->createAccessDeniedException();
        }

        $this->pageService->restore($this->revisionOr404($entity, $revision), $this->currentUser(), 'Restored revision #' . $revision);
        $this->addFlash('success', 'wiki.page.restored');

        return $this->redirectToRoute('wiki_page_history', ['slug' => $wiki->getSlug(), 'page' => $page]);
    }

    #[Route('/protect', 'page_protect', methods: ['POST'])]
    public function protect(Wiki $wiki, string $page, Request $request): Response
    {
        $entity = $this->moderatablePage($wiki, $page, $request, 'wiki-protect');
        $this->pageService->setProtected($entity, !$entity->isProtected());
        $this->addFlash('success', $entity->isProtected() ? 'wiki.page.protected' : 'wiki.page.unprotected');

        return $this->redirectToRoute('wiki_page', ['slug' => $wiki->getSlug(), 'page' => $page]);
    }

    #[Route('/delete', 'page_delete', methods: ['POST'])]
    public function delete(Wiki $wiki, string $page, Request $request): Response
    {
        $this->pageService->delete($this->moderatablePage($wiki, $page, $request, 'wiki-delete'));
        $this->addFlash('success', 'wiki.page.deleted');

        return $this->redirectToRoute('wiki_special_all', ['slug' => $wiki->getSlug()]);
    }

    private function viewablePage(Wiki $wiki, string $slug): WikiPage
    {
        $this->assertCanView($wiki);

        return $this->pageRepository->findBySlug($wiki, $slug) ?? throw $this->createNotFoundException();
    }

    private function moderatablePage(Wiki $wiki, string $slug, Request $request, string $tokenId): WikiPage
    {
        $page = $this->viewablePage($wiki, $slug);
        $this->assertToken($request, $tokenId);
        if (!$this->accessService->canModerate($wiki)) {
            throw $this->createAccessDeniedException();
        }

        return $page;
    }

    private function assertToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function revisionOr404(WikiPage $page, int $id): WikiRevision
    {
        return $this->revisionRepository->findOnPage($page, $id) ?? throw $this->createNotFoundException();
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<int, WikiPage>
     */
    private function breadcrumbs(WikiPage $page): array
    {
        $trail = [];
        $seen = [$page->getId() => true];
        $current = $page->getParent();
        while ($current !== null && !isset($seen[$current->getId()])) {
            $seen[$current->getId()] = true;
            array_unshift($trail, $current);
            $current = $current->getParent();
        }

        return $trail;
    }

    private function findParent(Wiki $wiki, ?string $name): ?WikiPage
    {
        $slug = PageSlugger::slug((string)$name);

        return $slug !== '' ? $this->pageRepository->findBySlug($wiki, $slug) : null;
    }

    private function parentError(?WikiPage $page, ?string $name, ?WikiPage $parent): ?string
    {
        if (trim((string)$name) === '') {
            return null;
        }

        if ($parent === null) {
            return 'wiki.edit.error.parent_missing';
        }

        for ($current = $parent, $depth = 0; $current !== null && $depth < 100; $current = $current->getParent(), $depth++) {
            if ($page !== null && $current->getId() === $page->getId()) {
                return 'wiki.edit.error.parent_cycle';
            }
        }

        return null;
    }
}
