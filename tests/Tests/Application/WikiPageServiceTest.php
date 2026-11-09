<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\Entity\WikiPage;
use Citadel\Wiki\Exception\EditConflictException;
use Citadel\Wiki\Markdown\WikiMarkdownRenderer;
use Citadel\Wiki\Repository\WikiLinkRepository;
use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Repository\WikiRevisionRepository;
use Citadel\Wiki\Service\WikiPageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WikiPageServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private WikiPageService $pageService;
    private Wiki $wiki;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->pageService = $container->get(WikiPageService::class);

        $this->wiki = new Wiki();
        $this->wiki->setTitle('Omnicide');
        $this->em->persist($this->wiki);
        $this->em->flush();
    }

    public function testCreatingPageWritesRevisionHtmlAndLinks(): void
    {
        $page = $this->create('home', 'Home', "# Welcome\n\nSee [[Plasma Rifle|the rifle]].\n\n[[Category:Weapons]]");

        self::assertNotNull($page->getCurrentRevision());
        self::assertStringContainsString('wiki-link-missing', $page->getHtml());
        self::assertStringContainsString('the rifle', $page->getHtml());
        self::assertSame('wiki-welcome', $page->getToc()[0]['id']);
        self::assertSame(['category:weapons'], self::getContainer()->get(WikiLinkRepository::class)->findCategorySlugs($page));
    }

    public function testRawHtmlIsEscaped(): void
    {
        $html = $this->create('xss', 'XSS', "<script>alert(1)</script>\n\n[x](javascript:alert(1))")->getHtml();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testInfoboxEscapesFieldsAndRejectsUnsafeImage(): void
    {
        $html = $this->create('box', 'Box', "```infobox\ntitle: <b>Rifle</b>\nimage: javascript:alert(1)\nDamage: 45\n```")->getHtml();

        self::assertStringContainsString('wiki-infobox', $html);
        self::assertStringContainsString('&lt;b&gt;Rifle', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('Damage', $html);
    }

    public function testOrdinaryCodeBlocksAreUntouched(): void
    {
        self::assertStringContainsString('<code class="language-php">', $this->create('code', 'Code', "```php\necho 1;\n```")->getHtml());
    }

    public function testCreatingATargetTurnsRedLinksBlue(): void
    {
        $home = $this->create('home', 'Home', '[[Plasma Rifle]]');
        self::assertStringContainsString('wiki-link-missing', $home->getHtml());

        $rifle = $this->create('plasma-rifle', 'Plasma Rifle', 'Boom');
        self::assertStringNotContainsString('wiki-link-missing', $home->getHtml());

        $this->pageService->delete($rifle);
        self::assertStringContainsString('wiki-link-missing', $home->getHtml());
    }

    public function testStaleEditIsRejected(): void
    {
        $page = $this->create('home', 'Home', 'one');
        $staleBase = $page->getCurrentRevision()?->getId();
        $this->pageService->save($page, 'Home', 'two', null, baseRevisionId: $staleBase);

        $this->expectException(EditConflictException::class);
        $this->pageService->save($page, 'Home', 'three', null, baseRevisionId: $staleBase);
    }

    public function testUnchangedContentAddsNoRevision(): void
    {
        $page = $this->create('home', 'Home', "same\r\n");

        self::assertNull($this->pageService->save($page, 'Home', "same\n", null, baseRevisionId: $page->getCurrentRevision()?->getId()));
        self::assertSame(1, self::getContainer()->get(WikiRevisionRepository::class)->countForPage($page));
    }

    public function testUnchangedSaveRefreshesRenderedHtml(): void
    {
        $page = $this->create('home', 'Home', 'text');
        $page->setHtml('stale');

        $this->pageService->save($page, 'Home', 'text', null, baseRevisionId: $page->getCurrentRevision()?->getId());

        self::assertStringContainsString('<p>text</p>', $page->getHtml());
    }

    public function testRestoreAddsANewRevisionWithOldContent(): void
    {
        $page = $this->create('home', 'Home', 'old');
        $first = $page->getCurrentRevision();
        self::assertNotNull($first);
        $this->pageService->save($page, 'Home', 'new', null, baseRevisionId: $first->getId());

        $restored = $this->pageService->restore($first, null, 'Restored');

        self::assertNotNull($restored);
        self::assertNotSame($first->getId(), $restored->getId());
        self::assertSame("old\n", $page->getContent());
        self::assertSame(0, $restored->getSizeDelta());
    }

    public function testWantedPagesAndTitleSearch(): void
    {
        $this->create('home', 'Home', '[[Missing One]] [[Category:Ignored]]');
        $repository = self::getContainer()->get(WikiLinkRepository::class);

        self::assertSame([['slug' => 'missing-one', 'links' => 1]], array_map(
            static fn (array $row) => ['slug' => $row['slug'], 'links' => (int)$row['links']],
            $repository->findWanted($this->wiki, 10, 0),
        ));
        self::assertCount(1, self::getContainer()->get(WikiPageRepository::class)->search($this->wiki, 'Hom'));
    }

    public function testVisibleCategoryLinkDoesNotAddMembership(): void
    {
        $page = $this->create('home', 'Home', '[[:Category:Weapons]]');

        self::assertSame([], self::getContainer()->get(WikiLinkRepository::class)->findCategorySlugs($page));
    }

    public function testRendererReportsMissingLinks(): void
    {
        $rendered = self::getContainer()->get(WikiMarkdownRenderer::class)->render($this->wiki, '[[Nope Page]] and [[:Category:Weapons]]');

        self::assertSame(['nope-page' => 'Nope Page'], $rendered->missing);
        self::assertStringContainsString('Weapons', $rendered->html);
    }

    private function create(string $slug, string $title, string $content): WikiPage
    {
        $page = new WikiPage($this->wiki, $slug);
        $this->pageService->save($page, $title, $content, null);

        return $page;
    }
}
