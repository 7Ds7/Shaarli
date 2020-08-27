<?php

declare(strict_types=1);

namespace Shaarli\Container;

use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Visitor\ErrorController;
use Shaarli\History;
use Shaarli\Http\HttpAccess;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\CookieManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;
use Shaarli\Updater\Updater;
use Shaarli\Updater\UpdaterUtils;

/**
 * Class ContainerBuilder
 *
 * Helper used to build a Slim container instance with Shaarli's object dependencies.
 * Note that most injected objects MUST be added as closures, to let the container instantiate
 * only the objects it requires during the execution.
 *
 * @package Container
 */
class ContainerBuilder
{
    /** @var ConfigManager */
    protected $conf;

    /** @var SessionManager */
    protected $session;

    /** @var CookieManager */
    protected $cookieManager;

    /** @var LoginManager */
    protected $login;

    /** @var string|null */
    protected $basePath = null;

    public function __construct(
        ConfigManager $conf,
        SessionManager $session,
        CookieManager $cookieManager,
        LoginManager $login
    ) {
        $this->conf = $conf;
        $this->session = $session;
        $this->login = $login;
        $this->cookieManager = $cookieManager;
    }

    public function build(): ShaarliContainer
    {
        $container = new ShaarliContainer();

        $container['conf'] = $this->conf;
        $container['sessionManager'] = $this->session;
        $container['cookieManager'] = $this->cookieManager;
        $container['loginManager'] = $this->login;
        $container['basePath'] = $this->basePath;

        $container['plugins'] = function (ShaarliContainer $container): PluginManager {
            return new PluginManager($container->conf);
        };

        $container['history'] = function (ShaarliContainer $container): History {
            return new History($container->conf->get('resource.history'));
        };

        $container['bookmarkService'] = function (ShaarliContainer $container): BookmarkServiceInterface {
            return new BookmarkFileService(
                $container->conf,
                $container->history,
                $container->loginManager->isLoggedIn()
            );
        };

        $container['pageBuilder'] = function (ShaarliContainer $container): PageBuilder {
            return new PageBuilder(
                $container->conf,
                $container->sessionManager->getSession(),
                $container->bookmarkService,
                $container->sessionManager->generateToken(),
                $container->loginManager->isLoggedIn()
            );
        };

        $container['pluginManager'] = function (ShaarliContainer $container): PluginManager {
            $pluginManager = new PluginManager($container->conf);

            $pluginManager->load($container->conf->get('general.enabled_plugins'));

            return $pluginManager;
        };

        $container['formatterFactory'] = function (ShaarliContainer $container): FormatterFactory {
            return new FormatterFactory(
                $container->conf,
                $container->loginManager->isLoggedIn()
            );
        };

        $container['pageCacheManager'] = function (ShaarliContainer $container): PageCacheManager {
            return new PageCacheManager(
                $container->conf->get('resource.page_cache'),
                $container->loginManager->isLoggedIn()
            );
        };

        $container['feedBuilder'] = function (ShaarliContainer $container): FeedBuilder {
            return new FeedBuilder(
                $container->bookmarkService,
                $container->formatterFactory->getFormatter(),
                $container->environment,
                $container->loginManager->isLoggedIn()
            );
        };

        $container['thumbnailer'] = function (ShaarliContainer $container): Thumbnailer {
            return new Thumbnailer($container->conf);
        };

        $container['httpAccess'] = function (): HttpAccess {
            return new HttpAccess();
        };

        $container['netscapeBookmarkUtils'] = function (ShaarliContainer $container): NetscapeBookmarkUtils {
            return new NetscapeBookmarkUtils($container->bookmarkService, $container->conf, $container->history);
        };

        $container['updater'] = function (ShaarliContainer $container): Updater {
            return new Updater(
                UpdaterUtils::read_updates_file($container->conf->get('resource.updates')),
                $container->bookmarkService,
                $container->conf,
                $container->loginManager->isLoggedIn()
            );
        };

        $container['errorHandler'] = function (ShaarliContainer $container): ErrorController {
            return new ErrorController($container);
        };

        return $container;
    }
}