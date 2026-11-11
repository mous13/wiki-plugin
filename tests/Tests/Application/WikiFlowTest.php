<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Citadel\Wiki\Entity\Wiki;
use Citadel\Wiki\MenuBuilder\MenuType\WikiMenuType;
use Citadel\Wiki\Repository\WikiPageRepository;
use Citadel\Wiki\Widget\EmbedPageWidget;
use Citadel\Wiki\Widget\RecentChangesWidget;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Admin\Form\MenuItemType;
use Forumify\Cms\Entity\Resource;
use Forumify\Cms\Entity\Snippet;
use Forumify\Core\Entity\MenuItem;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\ACLTrait;
use Forumify\Testing\Traits\UserTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;

class WikiFlowTest extends WebTestCase
{
    use ACLTrait;
    use UserTrait;

    private KernelBrowser $client;
    private Wiki $wiki;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->wiki = new Wiki();
        $this->wiki->setTitle('Omnicide');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->wiki);
        $em->flush();
    }

    public function testViewRequiresAcl(): void
    {
        $this->client->request('GET', '/wiki/omnicide/special/all');
        self::assertResponseRedirects('http://localhost/login');

        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/wiki/omnicide/special/all');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexOnlyListsVisibleWikis(): void
    {
        $this->client->request('GET', '/wiki');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Omnicide');

        $this->allow('view', 'guest');
        $this->client->request('GET', '/wiki');
        self::assertSelectorTextContains('.card-title', 'Omnicide');
    }

    public function testGuestCannotEditEvenWithAcl(): void
    {
        $this->allow('view', 'guest');
        $this->allow('edit', 'guest');

        $this->client->request('GET', '/wiki/omnicide/home/edit');

        self::assertFalse($this->client->getResponse()->isSuccessful());
    }

    public function testFullEditingFlow(): void
    {
        $this->loginEditor();

        $this->client->request('GET', '/wiki/omnicide/home');
        self::assertResponseStatusCodeSame(404);

        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => "# Hi\n\n[[Plasma Rifle]]\n\n[[Category:Lore]]", 'summary' => 'first']);
        self::assertResponseRedirects('/wiki/omnicide/home');
        $this->client->followRedirect();
        self::assertSelectorExists('.wiki-link-missing');
        self::assertSelectorTextContains('.tag', 'Lore');

        $page = self::getContainer()->get(WikiPageRepository::class)->findBySlug($this->wiki, 'home');
        self::assertNotNull($page);
        $firstRevision = $page->getCurrentRevision()?->getId();

        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => "# Hi\n\nChanged\n\n[[Category:Lore]]", 'summary' => 'second']);
        self::assertResponseRedirects('/wiki/omnicide/home');

        $crawler = $this->client->request('GET', '/wiki/omnicide/home/history');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tbody tr'));

        $secondRevision = $firstRevision + 1;
        $this->client->request('GET', "/wiki/omnicide/home/history?a=$secondRevision&b=$firstRevision");
        self::assertResponseRedirects("/wiki/omnicide/home/diff/$firstRevision/$secondRevision");
        $this->client->followRedirect();
        self::assertSelectorExists('.wiki-diff-added');

        foreach (['recent', 'all', 'wanted', 'protected', 'search?q=home'] as $special) {
            $this->client->request('GET', '/wiki/omnicide/special/' . $special);
            self::assertResponseIsSuccessful($special);
        }

        $this->client->request('GET', '/wiki/omnicide/category:lore');
        self::assertResponseIsSuccessful();
    }

    public function testConflictKeepsTheEditorsText(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'one']);

        $crawler = $this->client->request('GET', '/wiki/omnicide/home/edit');
        $form = $crawler->filter('form[action$="/edit"]')->form();
        $staleBase = $form->get('wiki_page[base]')->getValue();

        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'two']);

        $form->setValues(['wiki_page[content]' => 'mine']);
        self::assertSame($staleBase, $form->get('wiki_page[base]')->getValue());
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.alert-warning');
        self::assertStringContainsString('mine', $crawler->filter('textarea[name="wiki_page[content]"]')->text());
    }

    public function testProtectedPageNeedsModerator(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'one']);

        $page = self::getContainer()->get(WikiPageRepository::class)->findBySlug($this->wiki, 'home');
        self::assertNotNull($page);
        $page->setProtected(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->request('GET', '/wiki/omnicide/home/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPageShowsContentsAndOpensItsCategoryInNavigation(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/category:lore/edit', ['title' => 'Category:Lore', 'content' => 'All lore. [[Category:World]]']);
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => "Lead.\n\n```infobox\ntitle: Box\n```\n\n## One\n\n### Two\n\n## Three\n\n[[Category:Lore]]"]);
        $this->submit('/wiki/omnicide/other/edit', ['title' => 'Other', 'content' => '[[Category:Unrelated]]']);

        $crawler = $this->client->request('GET', '/wiki/omnicide/home');

        self::assertResponseIsSuccessful();
        self::assertSame(['1', '1.1', '2'], $crawler->filter('.hide-phone .wiki-contents a span')->each(static fn ($node) => $node->text()));
        self::assertCount(2, $crawler->filter('.wiki-infobox'));
        self::assertSame(['World', 'Lore'], $crawler->filter('aside .wiki-accordion details[open] > summary')->each(static fn ($node) => $node->text()));
        self::assertSelectorExists('.wiki-accordion a[aria-current="page"]');
        self::assertSelectorNotExists('.wiki-navbox');
    }

    public function testCreatePageOpensEditorForNewTitle(): void
    {
        $this->loginEditor();
        $this->client->request('GET', '/wiki/omnicide/special/all');
        self::assertSelectorExists('a[href="/wiki/omnicide/special/create"]');

        $this->client->request('GET', '/wiki/omnicide/special/create?title=Plasma%20Rifle');
        self::assertResponseRedirects('/wiki/omnicide/plasma-rifle/edit?title=Plasma%20Rifle');

        $this->client->request('GET', '/wiki/omnicide/special/create?title=Category:Weapons');
        self::assertResponseRedirects('/wiki/omnicide/category:weapons/edit?title=Category:Weapons');
    }

    public function testCreatePageSendsExistingTitlesToThePage(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'one']);

        $this->client->request('GET', '/wiki/omnicide/special/create?title=home');

        self::assertResponseRedirects('/wiki/omnicide/home');
    }

    public function testCreatePageRejectsUnusableTitle(): void
    {
        $this->loginEditor();
        $this->client->request('GET', '/wiki/omnicide/special/create?title=!!!');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-error');
    }

    public function testViewersGetNoCreateButton(): void
    {
        $this->allow('view', 'user');
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/wiki/omnicide/special/all');
        self::assertSelectorNotExists('a[href="/wiki/omnicide/special/create"]');

        $this->client->request('GET', '/wiki/omnicide/special/create');
        self::assertResponseStatusCodeSame(403);
    }

    public function testResourceAndSnippetTagsExpandWhenViewed(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $snippet = new Snippet();
        $snippet->setName('Spoiler Notice');
        $snippet->setType('rich_text');
        $snippet->setContent('<p>Spoilers ahead</p>');
        $em->persist($snippet);
        $resource = new Resource();
        $resource->setName('Rifle Art');
        $resource->setPath('rifle.png');
        $em->persist($resource);
        $em->flush();

        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => "{{ snippet('spoiler-notice') }}\n\n![Art]({{ resource('rifle-art') }})\n\n```infobox\nimage: {{ resource('rifle-art') }}\n```\n\n`{{ resource('rifle-art') }}`\n\n{{ dump(app) }}"]);
        $crawler = $this->client->request('GET', '/wiki/omnicide/home');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Spoilers ahead', $crawler->filter('.wiki-article')->html());
        self::assertStringEndsWith('rifle.png', (string)$crawler->filter('.wiki-article > p img')->attr('src'));
        self::assertStringEndsWith('rifle.png', (string)$crawler->filter('aside .wiki-infobox img')->attr('src'));
        self::assertSame("{{ resource('rifle-art') }}", $crawler->filter('.wiki-article code')->text());
        self::assertStringContainsString('{{ dump(app) }}', $crawler->filter('.wiki-article')->text());
    }

    public function testContentsTagPlacesTheBox(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => "# Title\n\nIntro\n\n{{ contents }}\n\n## One\n\n## Two"]);

        $crawler = $this->client->request('GET', '/wiki/omnicide/home');
        $html = (string)$crawler->filter('.wiki-article')->html();

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('wiki-contents-here', $html);
        self::assertLessThan(strpos($html, 'wiki-contents'), strpos($html, 'Intro'));
        self::assertLessThan(strpos($html, 'id="wiki-one"'), strrpos($html, 'wiki-contents'));
    }

    public function testCategoryNamesLinkAndSubcategoriesAreGrouped(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/category:lore/edit', ['title' => 'Category:Lore', 'content' => 'All lore. [[Category:World]]']);
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'Text [[Category:World]]']);

        $crawler = $this->client->request('GET', '/wiki/omnicide/category:world');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside .wiki-accordion summary a[href="/wiki/omnicide/category:world"][aria-current="page"]');
        self::assertSelectorNotExists('aside .wiki-accordion a.pl-4');
        self::assertSelectorTextSame('h1', 'World');
        self::assertSame(['Lore', 'Home'], $crawler->filter('.wiki-main .box li a')->each(static fn ($node) => $node->text()));
    }

    public function testBlankContentIsRejected(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => '   ']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testWikiMenuItemLinksToTheChosenWiki(): void
    {
        $this->allow('view', 'guest');
        $item = $this->createWikiMenuItem(['wiki' => $this->wiki->getId()]);

        $html = self::getContainer()->get(WikiMenuType::class)->buildItem($item);

        self::assertStringContainsString("href='/wiki/omnicide'", $html);
        self::assertStringContainsString('Game wiki', $html);
    }

    public function testWikiMenuItemWithoutAWikiIsHidden(): void
    {
        $this->allow('view', 'guest');
        $item = $this->createWikiMenuItem([]);

        self::assertSame('', self::getContainer()->get(WikiMenuType::class)->buildItem($item));
    }

    public function testWikiMenuItemIsHiddenWithoutWikiAccess(): void
    {
        $item = $this->createWikiMenuItem(['wiki' => $this->wiki->getId()]);

        self::assertSame('', self::getContainer()->get(WikiMenuType::class)->buildItem($item));
    }

    public function testMenuBuilderOffersWikiTypeWithWikiChoice(): void
    {
        $item = new MenuItem();
        $item->setName('Game wiki');
        $item->setType('wiki');

        $form = self::getContainer()->get('form.factory')->create(MenuItemType::class, $item);

        self::assertContains('wiki', $form->get('type')->getConfig()->getOption('choices'));
        self::assertSame(['Omnicide' => $this->wiki->getId()], $form->get('payload')->get('wiki')->getConfig()->getOption('choices'));
        self::assertFalse($form->get('payload')->get('wiki')->getConfig()->getOption('multiple'));
    }

    public function testOverlongSlugIsNotFound(): void
    {
        $this->loginEditor();

        $this->client->request('GET', '/wiki/omnicide/' . str_repeat('a', 300) . '/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testWidgetsRenderOnlyWhenWikiIsVisible(): void
    {
        $this->loginEditor();
        $this->submit('/wiki/omnicide/home/edit', ['title' => 'Home', 'content' => 'Embedded text']);
        $this->client->request('GET', '/wiki/omnicide/home');

        $container = self::getContainer();
        $twig = $container->get(Environment::class);
        $settings = ['wiki' => $this->wiki->getId(), 'page' => 'Home', 'count' => 5];

        $recent = $container->get(RecentChangesWidget::class);
        self::assertNotNull($recent->getSettingsForm($settings));
        self::assertStringContainsString('Home', $twig->render($recent->getTemplate(), ['widget' => $recent->getTemplateContext($settings)]));

        $embed = $container->get(EmbedPageWidget::class);
        self::assertNotNull($embed->getSettingsForm($settings));
        self::assertStringContainsString('Embedded text', $twig->render($embed->getTemplate(), ['widget' => $embed->getTemplateContext($settings)]));

        $container->get('security.token_storage')->setToken(null);
        self::assertSame([], $recent->getTemplateContext($settings));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createWikiMenuItem(array $payload): MenuItem
    {
        $item = new MenuItem();
        $item->setName('Game wiki');
        $item->setType('wiki');
        $item->setPayload($payload);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($item);
        $em->flush();
        $this->createACL(MenuItem::class, $item->getId(), 'view', [$this->getGuestRole()]);

        return $item;
    }

    private function loginEditor(): void
    {
        $this->allow('view', 'user');
        $this->allow('edit', 'user');
        $user = $this->createUser();
        self::assertInstanceOf(User::class, $user);
        $this->client->loginUser($user);
    }

    private function allow(string $permission, string $role): void
    {
        $this->createACL(Wiki::class, $this->wiki->getId(), $permission, [$role === 'guest' ? $this->getGuestRole() : $this->getUserRole()]);
    }

    /**
     * @param array<string, string> $values
     */
    private function submit(string $url, array $values): void
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $fields = [];
        foreach ($values as $name => $value) {
            $fields["wiki_page[$name]"] = $value;
        }

        $this->client->submit($crawler->filter('form[action$="/edit"]')->form(), $fields);
    }
}
