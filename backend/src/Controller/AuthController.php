<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 64) {
            return $this->json(
                ['error' => 'Username must be between 3 and 64 characters.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (strlen($password) < 6) {
            return $this->json(
                ['error' => 'Password must be at least 6 characters.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($this->userRepository->findOneBy(['username' => $username])) {
            return $this->json(
                ['error' => 'Username is already taken.'],
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User($username, '');
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        // Re-create with hashed password since constructor sets it
        $user = new User($username, $hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'username' => $user->getUsername(),
            ],
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['error' => 'Invalid credentials.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'username' => $user->getUsername(),
            ],
            'token' => $token,
        ]);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
        ]);
    }
}
