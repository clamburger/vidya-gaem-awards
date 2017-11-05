<?php
namespace AppBundle\Controller;

use AppBundle\Service\ConfigService;
use AppBundle\Service\NavbarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use AppBundle\Entity\Action;
use AppBundle\Entity\Award;
use AppBundle\Entity\Config;
use AppBundle\Entity\Nominee;
use AppBundle\Entity\Vote;
use AppBundle\Entity\VotingCodeLog;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use VGA\Utils;

class VotingController extends Controller
{
    public function indexAction(?string $awardID, NavbarService $navbar, EntityManagerInterface $em, ConfigService $configService, Request $request, AuthorizationCheckerInterface $authChecker, UserInterface $user)
    {
        if (!$navbar->canAccessRoute('voting')) {
            throw $this->createAccessDeniedException();
        }

        /** @var Award[] $awards */
        $awards = $em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a', 'a.id')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC')
            ->getQuery()
            ->getResult();

        $prevAward = null;
        $nextAward = null;
        $voteJSON = [null];

        $config = $configService->getConfig();

        $start = $config->getVotingStart();
        $end = $config->getVotingEnd();

        $votingNotYetOpen = $config->isVotingNotYetOpen();
        $votingClosed = $config->hasVotingClosed();
        $votingOpen = $config->isVotingOpen();

        if ($votingNotYetOpen) {
            if (!$start) {
                $voteText = 'Voting will open soon.';
            } else {
                $voteText = 'Voting will open in ' . Config::getRelativeTimeString($start) . '.';
            }
        } elseif ($votingOpen) {
            if (!$end) {
                $voteText = 'Voting is now open!';
            } else {
                $voteText = 'You have ' . Config::getRelativeTimeString($end) . ' left to vote.';
            }
        } else {
            $voteText = 'Voting is now closed.';
        }

        // Users with special access to the voting page can change the current vote status for testing purposes
        if ($authChecker->isGranted('ROLE_VOTING_VIEW')) {
            $time = $request->get('time');
            if ($time === 'before') {
                $votingNotYetOpen = true;
                $votingOpen = $votingClosed = false;
                $voteText = 'Voting will open soon.';
            } elseif ($time === 'after') {
                $votingClosed = true;
                $votingNotYetOpen = $votingOpen = false;
                $voteText = 'Voting is now closed.';
            } elseif ($time === 'during') {
                $votingOpen = true;
                $votingNotYetOpen = $votingClosed = false;
                $voteText = 'Voting is now open!';
            }
        }

        /** @var Vote[] $votes */
        $votes = $em->createQueryBuilder()
            ->select('v')
            ->from(Vote::class, 'v')
            ->where('v.cookieID = :cookie')
            ->setParameter('cookie', $user->getRandomID())
            ->getQuery()
            ->getResult();

        $simpleVotes = [];
        foreach ($awards as $a) {
            $simpleVotes[$a->getId()] = [];
        }
        foreach ($votes as $vote) {
            $preferences = $vote->getPreferences();
            array_unshift($preferences, null);
            $simpleVotes[$vote->getAward()->getId()] = $preferences;
        }

        // Fetch the active award (if given)
        if ($awardID) {
            $repo = $em->getRepository(Award::class);

            /** @var Award $award */
            $award = $repo->find($awardID);

            if (!$award || !$award->isEnabled()) {
                $this->addFlash('error', 'Invalid award specified.');
                return $this->redirectToRoute('voting');
            }

            // Iterate through the awards list to get the previous and next awards
            $iterAward = reset($awards);
            while ($iterAward !== $award) {
                $prevAward = $iterAward;
                $iterAward = next($awards);
            }

            $nextAward = next($awards);
            if (!$nextAward) {
                $nextAward = reset($awards);
            }

            if (!$prevAward) {
                $prevAward = end($awards);
            }

            if (isset($simpleVotes[$award->getId()])) {
                $voteJSON = $simpleVotes[$award->getId()];
            }
        }

        return $this->render('voting.twig', [
            'title' => 'Voting',
            'awards' => $awards,
            'award' => $award ?? false,
            'votingNotYetOpen' => $votingNotYetOpen,
            'votingClosed' => $votingClosed,
            'votingOpen' => $votingOpen,
            'voteText' => $voteText,
            'prevAward' => $prevAward,
            'nextAward' => $nextAward,
            'votes' => $voteJSON,
            'allVotes' => $simpleVotes
        ]);
    }

    public function postAction($awardID, NavbarService $navbar, ConfigService $configService, AuthorizationCheckerInterface $authChecker, EntityManagerInterface $em, Request $request, UserInterface $user)
    {
        if (!$navbar->canAccessRoute('voting')) {
            throw $this->createAccessDeniedException();
        }

        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'Voting has closed.']);
        }

        if (!$authChecker->isGranted('ROLE_VOTING_VIEW')) {
            if ($configService->getConfig()->isVotingNotYetOpen()) {
                return $this->json(['error' => 'Voting hasn\'t started yet.']);
            } elseif ($configService->getConfig()->hasVotingClosed()) {
                return $this->json(['error' => 'Voting has closed.']);
            }
        }

        /** @var Award $award */
        $award = $em->getRepository(Award::class)->find($awardID);

        if (!$award || !$award->isEnabled()) {
            return $this->json(['error' => 'Invalid award specified.']);
        }

        $preferences = $request->request->get('preferences', ['']);

        // Remove blank preferences and recreate the key ordering.
        $preferences = array_values(array_filter($preferences));
        // By adding an element to the front and then removing it, we shift the keys from 0 to n to 1 to n+1.
        array_unshift($preferences, '');
        unset($preferences[0]);

        if (count($preferences) != count(array_unique($preferences))) {
            return $this->json(['error' => 'Duplicate nominees are not allowed.']);
        }

        $nomineeIDs = $award->getNominees()->map(function (Nominee $n) {
            return $n->getShortName();
        });
        $invalidNominees = array_diff($preferences, $nomineeIDs->toArray());

        if (count($invalidNominees) > 0) {
            return $this->json(
                ['error' => 'Some of the nominees you\'ve voted for are invalid: ' . implode(', ', $invalidNominees)]
            );
        }

        $query = $em->createQueryBuilder()
            ->select('v')
            ->from(Vote::class, 'v')
            ->join('v.award', 'a')
            ->where('a.id = :award')
            ->setParameter('award', $award->getId())
            ->andWhere('v.cookieID = :cookie')
            ->setParameter('cookie', $user->getRandomID());

        $vote = $query->getQuery()->getOneOrNullResult();

        if (count($preferences) === 0) {
            if ($vote) {
                $em->remove($vote);
                $em->flush();
            }
            return $this->json(['success' => true]);
        }

        if (!$vote) {
            $vote = new Vote();
            $vote
                ->setAward($award)
                ->setCookieID($user->getRandomID());
        }

        $vote
            ->setPreferences($preferences)
            ->setTimestamp(new \DateTime())
            ->setUser($user)
            ->setIp($user->getIP())
            ->setVotingCode($user->getVotingCode());
        $em->persist($vote);

        $action = new Action('voted');
        $action
            ->setUser($user)
            ->setPage(__CLASS__)
            ->setData1($award->getId());
        $em->persist($action);

        $em->flush();

        return $this->json(['success' => true]);
    }

    public function codeEntryAction($code, NavbarService $navbar, ConfigService $configService, Request $request, EntityManagerInterface $em, UserInterface $user, SessionInterface $session)
    {
        if (!$navbar->canAccessRoute('voting')) {
            throw $this->createAccessDeniedException();
        }

        $session->set('votingCode', $code);

        if (!$configService->isReadOnly()) {
            $log = new VotingCodeLog();
            $log
                ->setUser($user)
                ->setCode($code)
                ->setReferer($request->server->get('HTTP_REFERER'))
                ->setTimestamp(new \DateTime());

            $em->persist($log);
            $em->flush();
        }

        $response = $this->redirectToRoute('voting');
        $response->headers->setCookie(new Cookie(
            'votingCode',
            $code,
            new \DateTime('+90 days'),
            '/',
            $request->getHost()
        ));
        return $response;
    }

    public function codeViewerAction(RouterInterface $router)
    {
        $date = new \DateTime();
        $dateString = $date->format('M d Y, g A');

        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // This is an awful implementation, but will do for now
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $characters[Utils::randomNumber($dateString . $i, strlen($characters) - 1)];
        }

        $url = $router->generate('voteWithCode', ['code' => $code], UrlGenerator::ABSOLUTE_URL);
        $url = substr($url, 0, strrpos($url, '/') + 1);

        return $this->render('votingCode.twig', [
            'title' => 'Voting Code',
            'date' => $dateString,
            'url' => $url,
            'code' => $code
        ]);
    }
}