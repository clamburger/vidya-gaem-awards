<?php
use Ehesp\SteamLogin\SteamLogin;
use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use VGA\Controllers;
use VGA\DependencyContainer;
use VGA\DependencyManager;
use VGA\Model\Access;
use VGA\Model\AnonymousUser;
use VGA\Model\Config;
use VGA\Model\LoginToken;
use VGA\Model\User;

require(__DIR__ . '/../bootstrap.php');

// Basic setup
$em = DependencyManager::getEntityManager();
$request = Request::createFromGlobals();
$session = new Session();
$session->start();

// TODO: unsure if this is needed when using the Response class
header("Content-type: text/html; charset=utf-8");

// Check if the user is currently logged in
if ($session->get('user')) {
    $user = $em->getRepository(User::class)->find($session->get('user'));
} elseif ($cookie = $request->cookies->get('rememberMeToken')) {
    list($token, $hmac) = explode(':', $cookie, 2);
    $tokenValid = ($hmac == hash_hmac('md5', $token, STEAM_API_KEY));
    if ($tokenValid) {
        /** @var LoginToken $token */
        $token = $em->getRepository(LoginToken::class)->findBy(['token' => $token]);
        if ($token) {
            $user = $token->getUser();
        }
    }
}

if (!isset($user)) {
    $user = new AnonymousUser();
}

if ($user->canDo('view-debug-output')) {
    Debug::enable();
}

// Generate a random ID to keep in the cookie if one doesn't already exist.
// We use this cookie as part of the voting identification process
// (although it's similar to the remember me token, they serve different purposes)
$randomIDCookie = $request->cookies->get('access');
$randomIDSession = $session->get('access');

if ($randomIDCookie && $randomIDSession) {
    $randomID = $randomIDCookie;
} elseif ($randomIDSession && !$randomIDCookie) {
    // Bad practice, should be using Symfony's request class
    setcookie('access', $randomIDSession, strtotime('+90 days'), '/', $request->getHost());
    $randomID = $randomIDSession;
} elseif ($randomIDCookie && !$randomIDSession) {
    $session->set('access', $randomIDCookie);
    $randomID = $randomIDCookie;
} else {
    $factory = new \RandomLib\Factory;
    $generator = $factory->getLowStrengthGenerator();
    $randomID = hash('sha256', $generator->generate(64));
    $randomID .= ':' . hash_hmac('md5', $randomID, STEAM_API_KEY);

    // Bad practice, should be using Symfony's request class
    setcookie('access', $randomID, strtotime('+90 days'), '/', $request->getHost());
    $session->set('access', $randomID);
}

$votingCodeSession = $session->get('votingCode');
$votingCodeCookie = $request->cookies->get('votingCode');

if ($votingCodeCookie) {
    $session->set('votingCode', $votingCodeCookie);
    $votingCode = $votingCodeCookie;
} else {
    $votingCode = $votingCodeSession;
}

// Update the user object with information that doesn't come from the database.
$user
    ->setIP($request->server->get('HTTP_CF_CONNECTING_IP', $request->server->get('REMOTE_ADDR')))
    ->setRandomID($randomID)
    ->setVotingCode($votingCode);

/** @var Config $config */
$config = $em->getRepository(Config::class)->findOneBy([]);

// Define the routes
$routes = new RouteCollection();

$routes->add('home', new Route(
    '/home',
    [
        'controller' => Controllers\IndexController::class
    ]
));
$routes->add('news', new Route(
    '/news',
    [
        'controller' => Controllers\NewsController::class
    ]
));
$routes->add('newsAdd', new Route(
    '/news/add',
    [
        'controller' => Controllers\NewsController::class,
        'action' => 'add',
        'permission' => 'news-manage'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('newsDelete', new Route(
    '/news/delete/{id}',
    [
        'controller' => Controllers\NewsController::class,
        'action' => 'delete',
        'permission' => 'news-manage'
    ],
    ['id' => '\d+'],
    [],
    '',
    [],
    ['POST']
));
$routes->add('login', new Route(
    '/login/{return}',
    [
        'controller' => Controllers\AuthController::class,
        'action' => 'login'
    ],
    ['return' => '.*']
));
$routes->add('logout', new Route(
    '/logout',
    [
        'controller' => Controllers\AuthController::class,
        'action' => 'logout'
    ]
));

$routes->add('people', new Route(
    '/crew',
    [
        'controller' => Controllers\PeopleController::class,
        'permission' => 'profile-view'
    ]
));

$peopleCollection = new RouteCollection();
$peopleCollection->add('permissions', new Route(
    '/permissions',
    [
        'action' => 'permissions',
        'permission' => 'profile-view'
    ]
));
$peopleCollection->add('addPerson', new Route(
    '/new',
    [
        'action' => 'new',
        'permission' => 'add-user'
    ]
));
$peopleCollection->add('userSearch', new Route(
    '/search',
    [
        'action' => 'search',
        'permission' => 'add-user'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$peopleCollection->add('viewPerson', new Route(
    '/{steamID}',
    [
        'action' => 'view',
        'permission' => 'profile-view'
    ],
    ['steamID' => '\d+']
));
$peopleCollection->add('editPerson', new Route(
    '/{steamID}/edit',
    [
        'action' => 'edit',
        'permission' => 'profile-edit-details'
    ],
    ['steamID' => '\d+'],
    [],
    '',
    [],
    ['GET']
));
$peopleCollection->add('editPersonPost', new Route(
    '/{steamID}/edit',
    [
        'action' => 'post',
        'permission' => 'profile-view'
    ],
    ['steamID' => '\d+'],
    [],
    '',
    [],
    ['POST']
));
$peopleCollection->addPrefix('/crew');
$peopleCollection->addDefaults([
    'controller' => Controllers\PeopleController::class
]);
$routes->addCollection($peopleCollection);
$routes->add('privacy', new Route(
    '/privacy',
    [
        'controller' => Controllers\StaticController::class,
        'action' => 'privacy'
    ]
));
$routes->add('awards', new Route(
    '/awards',
    [
        'controller' => Controllers\AwardController::class,
        'permission' => $config->isPagePublic('awards') ? false : 'awards-edit'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('awardManager', new Route(
    '/awards/manage',
    [
        'controller' => Controllers\AwardAdminController::class,
        'action' => 'managerList',
        'permission' => 'awards-feedback'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('awardManagerPost', new Route(
    '/awards/manage',
    [
        'controller' => Controllers\AwardAdminController::class,
        'action' => 'managerPost',
        'permission' => 'awards-edit'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('awardManagerPostAjax', new Route(
    '/awards/manage/ajax',
    [
        'controller' => Controllers\AwardAdminController::class,
        'action' => 'managerPostAjax',
        'permission' => 'awards-edit'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('videoGames', new Route(
    '/vidya-in-2017',
    [
        'controller' => Controllers\VideoGamesController::class,
        'permission' => $config->isPagePublic('videoGames') ? false : 'add-video-game'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('addVideoGame', new Route(
    '/vidya-in-2017',
    [
        'controller' => Controllers\VideoGamesController::class,
        'action' => 'add',
        'permission' => 'add-video-game'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('awardFrontendPost', new Route(
    '/awards',
    [
        'controller' => Controllers\AwardController::class,
        'action' => 'post',
        'permission' => $config->isPagePublic('awards') ? false : 'awards-edit',
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('referrers', new Route(
    '/referrers',
    [
        'controller' => Controllers\ReferrerController::class,
        'permission' => 'referrers-view'
    ]
));
$routes->add('nomineeManager', new Route(
    '/nominees/{awardID}',
    [
        'controller' => Controllers\NomineeController::class,
        'permission' => 'nominations-view',
        'awardID' => null
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('nomineePost', new Route(
    '/nominees/{awardID}',
    [
        'controller' => Controllers\NomineeController::class,
        'action' => 'post',
        'permission' => 'nominations-edit'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('config', new Route(
    '/config',
    [
        'controller' => Controllers\ConfigController::class,
        'permission' => 'edit-config'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('configPost', new Route(
    '/config',
    [
        'controller' => Controllers\ConfigController::class,
        'action' => 'post',
        'permission' => 'edit-config'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('viewVotingCode', new Route(
    '/vote/code',
    [
        'controller' => Controllers\VotingController::class,
        'action' => 'codeViewer',
        'permission' => 'voting-view'
    ]
));
$routes->add('voting', new Route(
    '/vote/{awardID}',
    [
        'controller' => Controllers\VotingController::class,
        'awardID' => null,
        'permission' => $config->isPagePublic('voting') ? false : 'voting-view'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('votingSubmission', new Route(
    '/vote/{awardID}',
    [
        'controller' => Controllers\VotingController::class,
        'action' => 'post',
        'permission' => $config->isPagePublic('voting') ? false : 'voting-view'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('voteWithCode', new Route(
    '/vote/v/{code}',
    [
        'controller' => Controllers\VotingController::class,
        'action' => 'codeEntry'
    ]
));
$routes->add('simpleResults', new Route(
    '/winners',
    [
        'controller' => Controllers\ResultController::class,
        'action' => 'simple',
        'permission' => $config->isPagePublic('results') ? false : 'voting-results'
    ],
    [],
    [],
    '',
    [],
    ['GET']
));
$routes->add('winnerImageUpload', new Route(
    '/winners',
    [
        'controller' => Controllers\ResultController::class,
        'action' => 'winnerImageUpload',
        'permission' => 'awards-edit'
    ],
    [],
    [],
    '',
    [],
    ['POST']
));
$routes->add('detailedResults', new Route(
    '/results/{all}',
    [
        'controller' => Controllers\ResultController::class,
        'action' => 'detailed',
        'all' => null,
        'permission' => $config->isPagePublic('results') ? false : 'voting-results'
    ],
    [
        'all' => '(all)?'
    ]
));
$routes->add('resultRedirect', new Route(
    '/voting/results',
    [
        'controller' => Controllers\StaticController::class,
        'action' => 'votingRedirect'
    ]
));
$routes->add('pairwiseResults', new Route(
    '/results/pairwise',
    [
        'controller' => Controllers\ResultController::class,
        'action' => 'pairwise',
        'permission' => $config->isPagePublic('results') ? false : 'voting-results'
    ]
));
$routes->add('countdown', new Route(
    '/countdown',
    [
        'controller' => Controllers\LauncherController::class,
        'action' => 'countdown',
        'permission' => $config->isPagePublic('countdown') ? false : 'view-unfinished-pages'
    ]
));
$routes->add('stream', new Route(
    '/stream',
    [
        'controller' => Controllers\LauncherController::class,
        'action' => 'stream',
        'permission' => $config->isPagePublic('stream') ? false : 'view-unfinished-pages'
    ]
));
$routes->add('finished', new Route(
    '/finished',
    [
        'controller' => Controllers\LauncherController::class,
        'action' => 'finished',
        'permission' => $config->isPagePublic('finished') ? false : 'view-unfinished-pages'
    ]
));
$routes->add('credits', new Route(
    '/credits',
    [
        'controller' => Controllers\CreditsController::class,
        'permission' => $config->isPagePublic('credits') ? false : 'view-unfinished-pages'
    ]
));
$routes->add('videos', new Route(
    '/videos',
    [
        'controller' => Controllers\StaticController::class,
        'action' => 'videos',
        'permission' => $config->isPagePublic('videos') ? false : 'view-unfinished-pages'
    ]
));
$routes->add('soundtrack', new Route(
    '/soundtrack',
    [
        'controller' => Controllers\StaticController::class,
        'action' => 'soundtrack',
//        'permission' => $config->isPagePublic('music') ? false : 'view-unfinished-pages'
    ]
));

$defaultRoute = $routes->get($config->getDefaultPage());

if ($defaultRoute) {
    $index = clone $defaultRoute;
    $index->setPath('/');
    $routes->add('index', $index);
}

$context = new RequestContext();
$context->fromRequest($request);
// Due to the way that Cloudflare is set up, the user sees HTTPS but our server only sees HTTP.
// We manually update some values to pretend we have full HTTPS, else generated links will have the wrong protocol.
$_SERVER['HTTPS'] = 'on';
$context->setScheme('https');

$generator = new UrlGenerator($routes, $context);

// We can't instantiate Twig object any earlier as we need the UrlGenerator
$twig = DependencyManager::getTwig($generator);

// Steam login link
if ($user instanceof AnonymousUser) {
    $returnLink = $generator->generate(
        'login',
        ['return' => $request->getPathInfo()],
        UrlGenerator::ABSOLUTE_URL
    );

    $steam = new SteamLogin();
    $twig->addGlobal('steamLoginLink', $steam->url($returnLink));
}

$navbar = [];
foreach (NAVBAR_ITEMS as $routeName => $title) {
    if ($route = $routes->get($routeName)) {
        // Only show items in the menu if the user has access to them
        $permission = $route->getDefault('permission');
        if (!$permission || $user->canDo($permission)) {
            $navbar[$routeName] = $title;
        }
    }
}

$twig->addGlobal('navbarItems', $navbar);

$matcher = new UrlMatcher($routes, $context);

$container = new DependencyContainer(
    $em,
    $request,
    $twig,
    $session,
    $user,
    $generator,
    $config
);

// Call the correct controller and method
try {
    $match = $matcher->match($request->getPathInfo());

    if (!class_exists($match['controller'])) {
        $controller = new Controllers\ErrorController($container);
        $controller->internalErrorAction();
        return;
    }

    /** @var Controllers\BaseController $controller */
    $controller = new $match['controller']($container);

    if (isset($match['action'])) {
        $action = $match['action'] . 'Action';
    } else {
        $action = 'indexAction';
    }

    if (isset($match['permission']) && $match['permission'] !== false && !$user->canDo($match['permission'])) {
        /** @var Controllers\ErrorController $controller */
        $controller = new Controllers\ErrorController($container);
        if ($user->isLoggedIn()) {
            $controller->noAccessAction();
        } else {
            $controller->needLoginAction();
        }
        return;
    }

    if (!method_exists($controller, $action)) {
        /** @var Controllers\ErrorController $controller */
        $controller = new Controllers\ErrorController($container);
        $controller->internalErrorAction();
        return;
    }

    // Log the page access
    $access = new Access();
    if (!($user instanceof AnonymousUser)) {
        $access->setUser($user);
    }

    if (!$config->isReadOnly()) {
        $access
            ->setCookieID($user->getRandomID())
            ->setPage($match['_route'])
            ->setRequestMethod($request->server->get('REQUEST_METHOD'))
            ->setRequestString($request->server->get('REQUEST_URI'))
            ->setIp($user->getIP())
            ->setUserAgent($request->server->get('HTTP_USER_AGENT', ''))
            ->setFilename($request->server->get('SCRIPT_FILENAME'))
            ->setReferer($request->server->get('HTTP_REFERER'));
        $em->persist($access);
        $em->flush();
    }

    unset($match['controller']);
    unset($match['action']);
    unset($match['permission']);
    unset($match['_route']);
    call_user_func_array([$controller, $action], $match);

} catch (ResourceNotFoundException $e) {
    $controller = new Controllers\ErrorController($container);
    $controller->notFoundAction();
    return;
} catch (MethodNotAllowedException $e) {
    $controller = new Controllers\ErrorController($container);
    $controller->wrongMethodAction();
    return;
}

//$ACCESS = array(
//    // Volatile pages
//    "applications" => "applications-view",
//    "test" => EVERYONE,
//    "volunteer-submission" => LOGIN,
//    "videos" => EVERYONE,
//);
//
//// Pages so basic they don't need a PHP file.
//$noPHP = array(
//    "about" => "About",
//    "videos" => "Video Submission",
//);
//
//$noContainer = array(
//    "videos"
//);