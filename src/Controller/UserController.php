<?php


namespace App\Controller;


use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{

    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @Route("/user/{username}/picture", name="app_user_profile_picture")
     */
    public function userProfilePicture($username): Response
    {
        // placeholder
        return new BinaryFileResponse("../public/images/user.png");
    }

    /**
     * @Route("/admin/users", name="app_user_list")
     */
    public function userList(): Response
    {
        if (!$this->isGranted("ROLE_ADMIN")) {
            // not logged in
            throw new AccessDeniedHttpException();
        }

        $users = $this->userService->getUsers();

        return $this->render("user/users.html.twig", [
            "users" => $users
        ]);
    }
}