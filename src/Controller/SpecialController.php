<?php

declare(strict_types=1);

namespace Citadel\Wiki\Controller;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Repository\WikiLinkRepository;
use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Repository\WikiRevisionRepository;
use Citadel\Wiki\Service\PageSlugger;
use Citadel\Wiki\Service\SearchSnippet;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{slug:wiki}/special', 'special_')]
class SpecialController extends AbstractWikiController
{
    #[Route('/recent', 'recent')]
    public function recent(Wiki $wiki, Request $request, WikiRevisionRepository $revisions): Response
    {
        $this->assertCanView($wiki);
        $page = $this->pageNumber($request);

        return $this->render('@CitadelWikiPlugin/frontend/special/recent.html.twig', [
            'wiki' => $wiki,
            'pager' => $this->paginate($revisions->findRecent($wiki, self::PER_PAGE + 1, $this->offset($page)), $page),
        ]);
    }

    #[Route('/all', 'all')]
    public function all(Wiki $wiki, Request $request, WikiPageRepository $pages): Response
    {
        return $this->list($wiki, $request, $pages, false);
    }

    #[Route('/protected', 'protected')]
    public function protected(Wiki $wiki, Request $request, WikiPageRepository $pages): Response
    {
        return $this->list($wiki, $request, $pages, true);
    }

    #[Route('/wanted', 'wanted')]
    public function wanted(Wiki $wiki, Request $request, WikiLinkRepository $links): Response
    {
        $this->assertCanView($wiki);
        $page = $this->pageNumber($request);
        $rows = array_map(static fn (array $row): array => $row + [
            'title' => PageSlugger::humanize($row['slug']),
        ], $links->findWanted($wiki, self::PER_PAGE + 1, $this->offset($page)));

        return $this->render('@CitadelWikiPlugin/frontend/special/wanted.html.twig', [
            'wiki' => $wiki,
            'pager' => $this->paginate($rows, $page),
        ]);
    }

    #[Route('/search', 'search')]
    public function search(Wiki $wiki, Request $request, WikiPageRepository $pages): Response
    {
        $this->assertCanView($wiki);
        $query = trim($request->query->getString('q'));

        return $this->render('@CitadelWikiPlugin/frontend/special/search.html.twig', [
            'wiki' => $wiki,
            'query' => $query,
            'results' => array_map(static fn (WikiPage $page): array => [
                'page' => $page,
                'snippet' => SearchSnippet::fromMarkdown($page->getContent()),
            ], mb_strlen($query) >= 2 ? $pages->search($wiki, $query) : []),
        ]);
    }

    #[Route('/create', 'create')]
    public function create(Wiki $wiki, Request $request, WikiPageRepository $pages): Response
    {
        $this->assertCanView($wiki);
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->accessService->canEdit($wiki)) {
            throw $this->createAccessDeniedException();
        }

        $title = trim($request->query->getString('title'));
        $slug = PageSlugger::slug($title);
        if ($slug !== '') {
            if ($pages->findBySlug($wiki, $slug) !== null) {
                $this->addFlash('info', 'wiki.create.exists');

                return $this->redirectToRoute('wiki_page', ['slug' => $wiki->getSlug(), 'page' => $slug]);
            }

            return $this->redirectToRoute('wiki_page_edit', ['slug' => $wiki->getSlug(), 'page' => $slug, 'title' => $title]);
        }

        return $this->render('@CitadelWikiPlugin/frontend/special/create.html.twig', [
            'wiki' => $wiki,
            'title' => $title,
            'invalid' => $request->query->has('title'),
        ]);
    }

    private function list(Wiki $wiki, Request $request, WikiPageRepository $pages, bool $protectedOnly): Response
    {
        $this->assertCanView($wiki);
        $page = $this->pageNumber($request);

        return $this->render('@CitadelWikiPlugin/frontend/special/all.html.twig', [
            'wiki' => $wiki,
            'protectedOnly' => $protectedOnly,
            'pager' => $this->paginate($pages->findList($wiki, self::PER_PAGE + 1, $this->offset($page), $protectedOnly), $page),
        ]);
    }
}
