<?php

namespace App\Controller;

use App\Form\LoginFormType;
use App\Service\ApiService;
use App\Form\RegistrationFormType;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserController extends AbstractController
{

    public ApiService $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;

    }


    #[Route('/accueil', name: 'accueil')]
    public function acceuil()
    {
        return $this->render('accueil.html.twig');
    }

    #[Route('/register', name: 'register')]
    public function register(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);
        $apiResponse = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = [
                'email' => $form->get('email')->getData(),
                'password' => $form->get('password')->getData(),
                'username' => $form->get('username')->getData()
            ];
            try {
                $apiResponse = $this->apiService->postData('/api/register', $data, '');
                $apiResponseContent = $apiResponse->getBody()->getContents();
                $apiResponseContentArray = json_decode($apiResponseContent, true);
                $message = $apiResponseContentArray['message'];
                $this->addFlash('success', $message);
                return $this->redirectToRoute('login'); // route = route name
            } catch (ClientException $e) {
                $responseContent = $e->getResponse()->getBody()->getContents(); // du ocup getResponse existe la
                $responseContentArray = json_decode($responseContent, true);
                $errorMessage = $responseContentArray['message'] ?? 'An error occurred';
                $this->addFlash('error', $errorMessage);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                // si pas une exception de l'api on peut catch x fois par type d'exception
                // et du coup etre plus precis sur son handle d'erreur
            }
        }
        return $this->render('registration.html.twig', [
            'registrationForm' => $form->createView(),
            'apiResponse' => $apiResponse,
            'roles' => []

        ]);
    }

    #[Route('/login', name: 'login')]
    public function login(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);
        $apiResponse = null;
        $session = $request->getSession();
        if ($form->isSubmitted() && $form->isValid()) {
            $data = [
                'password' => $form->get('password')->getData(),
                'username' => $form->get('username')->getData()
            ];
            try {
                $apiResponse = $this->apiService->postData('/api/login_check', $data, '');
                $responseContent = $apiResponse->getBody()->getContents();
                $responseContentArray = json_decode($responseContent, true);
                $token = $responseContentArray['token'];
                $tokenParts = explode(".", $token);
                $tokenPayload = base64_decode($tokenParts[1]);
                $decodedPayload = json_decode($tokenPayload, true);

                $session->set('jwt_token', $token);
//                dd($session);
//                dd($this->apiService->fetchData('users/'.$decodedPayload['id'], $token));
                $userData = $this->apiService->fetchData('/api/users/'.$decodedPayload['id'], $token);
                $userId = $userData['id'];
                $userEmail = $userData['email'];
                $username = $userData['username'];
                $userLevel = substr($userData['level'], 12);
                $userRoles = $userData['roles'];
                $userInventory = $userData['inventory'];
                $userQuestProgress = $userData['questProgress'];
                $userTasks = $userData['task'];
                $session->set('id', $userId);
                $session->set('email', $userEmail);
                $session->set('username', $username);
                $session->set('level', $userLevel);
                $session->set('roles', $userRoles);
                $session->set('inventory', $userInventory);
                $session->set('questProgress', $userQuestProgress);
                $session->set('tasks', $userTasks);
//                dd($session);

//                dd($token);
                return $this->render('profile.html.twig', [
                    'roles'=>$userRoles,
                    'username'=>$username,
                ]);
            } catch (\Exception $e) {
                dd($e);
            }
        }
        return $this->render('login.html.twig', [
            'LoginForm' => $form->createView(),
            'apiResponse' => $apiResponse,
            'roles'=>$session->get('roles'),
            'username' => $session->get('username')
        ]);
    }

    #[Route('/inventory', name: 'inventory')]
    public function inventory(Request $request)
    {
        $session = $request->getSession();
        $sessionData = $session->all();
        $token = $sessionData['jwt_token'];
        $inventoryId = $sessionData['inventory'];
        $inventory = $this->apiService->fetchData($inventoryId, $token);
//        dd($sessionData['id']);
//        $boostersEndpoint = $inventoryId . '/boosters';
//        $allBoosters = $this->apiService->fetchData($boostersEndpoint, $token);

        $allPictures = $this->apiService->fetchData('/api/pictures', $token);
//        dd($inventory);


        $commonBooster = 0;
        $uncommonBooster = 0;
        $rareBooster = 0;
        $epicBooster = 0;



        foreach($inventory['boosters'] as $booster){
//            dd($inventoryId);
//            dd($booster['inventory']);
                if($booster['rarity'] === 0){
                    $commonBooster ++;
                }
                if($booster['rarity'] === 1){
                    $uncommonBooster ++;
                }
                if($booster['rarity'] === 2){
                    $rareBooster ++;
                }
                if($booster['rarity'] === 3){
                    $epicBooster ++;
                }

        }



//        dd($inventory);
        return $this->render('inventory.html.twig', [
            'roles'=>$session->get('roles'),
            'username' => $session->get('username'),
            'commonBooster' => $commonBooster,
            'uncommonBooster'=>$uncommonBooster,
            'rareBooster' => $rareBooster,
            'epicBooster' => $epicBooster,
            'inventory' => $inventory
        ]);
    }


    #[Route('/burn', name: 'burn')]
    public function burn(request $request): JsonResponse
    {
        $session = $request->getSession();
        $sessionData = $session->all();
//        dd($sessionData['id']);
        $token = $sessionData['jwt_token'];
        $this->apiService->postData('/api/burn', ['id' => $sessionData['id']], $token );
        return new JsonResponse(['status' =>'success']);
    }

    #[Route('/lock/{id}', name: 'lock')]
    public function lock(int $id, Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $sessionData = $session->all();
            $token = $sessionData['jwt_token'];
            $this->apiService->patchData('/api/pictures/'.$id, ['locked' => true], $token );
            return new JsonResponse(['status' =>'locked']);
        } catch(\Exception $e){
            return new JsonResponse(['status' =>'false']);
        }
    }

    #[Route('/unlock/{id}', name: 'unlock')]
    public function unlock(int $id, Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();
            $sessionData = $session->all();
            $token = $sessionData['jwt_token'];
            $this->apiService->patchData('/api/pictures/'.$id, ['locked' => false], $token );
            return new JsonResponse(['status' =>'unlocked']);
        } catch(\Exception $e){
            return new JsonResponse(['status' => 'false']);
        }


    }

    #[Route('/openBoosters/{rarity}', name: 'openBoosters')]
    public function openBoosters(string $rarity, Request $request): JsonResponse
    {
        $session = $request->getSession();
        $sessionData = $session->all();
        $token = $sessionData['jwt_token'];
        $inventoryId = $sessionData['inventory'];
        $inventory = $this->apiService->fetchData($inventoryId, $token);
//        dd($rarity);

        if($rarity === '0'){
            foreach($inventory['boosters'] as $booster){
                if($booster['rarity'] === 0){
                    $idBooster = $booster['id'];
                    break;
                }
            }
        }
        if($rarity === '1'){
            foreach($inventory['boosters'] as $booster){
                if($booster['rarity'] === 1){
                    $idBooster = $booster['id'];
                    break;
                }
            }
        }
        if($rarity === '2'){
            foreach($inventory['boosters'] as $booster){
                if($booster['rarity'] === 2){
                    $idBooster = $booster['id'];
                    break;
                }
            }
        }
        if($rarity === '3'){
            foreach($inventory['boosters'] as $booster){
                if($booster['rarity'] === 3){
                    $idBooster = $booster['id'];
                    break;
                }
            }
        }
        if($idBooster){ // Todo peut etre undefined
            $apiResponse = $this->apiService->postData('/api/pkmImg', ['boosterId' => $idBooster], $token);
            $responseContent = $apiResponse->getBody()->getContents();
            $responseContentArray = json_decode($responseContent, true);
//            dd($responseContentArray);

            // todo c'est de la daube
            $card1 = $responseContentArray['cards'][0];
            $card2 = $responseContentArray['cards'][1];
            $card3 = $responseContentArray['cards'][2];
            $card4 = $responseContentArray['cards'][3];
            $card5 = $responseContentArray['cards'][4];
            return new JsonResponse(['card1'=>$card1, 'card2'=>$card2, 'card3'=>$card3,'card4'=>$card4,'card5'=>$card5]);
        }
        return new JsonResponse(['message','une erreur est survenue']);
    }
}