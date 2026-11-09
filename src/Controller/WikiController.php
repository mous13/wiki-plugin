<?php

declare(strict_types=1);

namespace Citadel\Wiki\Controller;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Repository\WikiRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WikiController extends AbstractWikiController
{
    public const HOME_SLUG = 'home';

    #[Route('', 'index')]
    public function index(WikiRepository $wikiRepository): Response
    {
        return $this->render('@CitadelWikiPlugin/frontend/index.html.twig', [
            'wikis' => $wikiRepository->findVisible(),
        ]);
    }

    #[Route('/{slug:wiki}', 'home')]
    public function home(Wiki $wiki): Response
    {
        $this->assertCanView($wiki);

        return $this->redirectToRoute('wiki_page', ['slug' => $wiki->getSlug(), 'page' => self::HOME_SLUG]);
    }
}
