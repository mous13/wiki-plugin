<?php

declare(strict_types=1);

namespace Citadel\Wiki\Controller;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Service\WikiAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractWikiController extends AbstractController
{
    protected const PER_PAGE = 50;

    protected WikiAccessService $accessService;

    #[Required]
    public function setAccess(WikiAccessService $accessService): void
    {
        $this->accessService = $accessService;
    }

    protected function assertCanView(Wiki $wiki): void
    {
        if (!$this->accessService->canView($wiki)) {
            throw $this->createAccessDeniedException();
        }
    }

    protected function pageNumber(Request $request): int
    {
        return max(1, $request->query->getInt('p', 1));
    }

    protected function offset(int $pageNumber): int
    {
        return ($pageNumber - 1) * self::PER_PAGE;
    }

    /**
     * @template T
     * @param array<T> $rows fetched with a limit of PER_PAGE + 1
     * @return array{rows: array<T>, page: int, hasNext: bool}
     */
    protected function paginate(array $rows, int $pageNumber): array
    {
        return [
            'rows' => array_slice($rows, 0, self::PER_PAGE),
            'page' => $pageNumber,
            'hasNext' => count($rows) > self::PER_PAGE,
        ];
    }
}
