<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class DonateurAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        // Le formulaire utilise 'email' et 'password' (configuré dans security.yaml)
        // Mais on supporte aussi '_username' et '_password' pour compatibilité
        $email = $request->request->get('email') ?? $request->request->get('_username');
        $password = $request->request->get('password') ?? $request->request->get('_password');
        $csrfToken = $request->request->get('_csrf_token');

        if (!$email || !$password) {
            throw new \InvalidArgumentException('Email et mot de passe sont requis.');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $roles = $user->getRoles();

        // 🔴 ADMIN ou ADMINISTRATEUR - Redirection vers le dashboard admin
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ADMINISTRATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        // 🔵 DONATEUR (ROLE_USER ou ROLE_DONATEUR) - Redirection vers le dashboard donateur
        if (in_array('ROLE_USER', $roles, true) || in_array('ROLE_DONATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('donateur_dashboard'));
        }

        // Si une page protégée était demandée
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Sécurité fallback - Redirection vers le dashboard donateur par défaut
        return new RedirectResponse($this->urlGenerator->generate('donateur_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
