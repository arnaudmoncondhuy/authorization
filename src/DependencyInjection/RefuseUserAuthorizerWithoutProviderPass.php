<?php

declare(strict_types=1);

namespace ArnaudMoncondhuy\Authorization\DependencyInjection;

use ArnaudMoncondhuy\Authorization\UserAuthorizer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Refuse « répondre sur un tiers » à qui s'en sert là où aucun compte ne peut être chargé, et
 * ne dit rien à qui ne s'en sert pas.
 *
 * {@see UserAuthorizer} désigne l'autre par son identifiant, et l'adaptateur le charge par le
 * fournisseur de comptes de l'application. Une application qui n'en déclare aucun — un pare-feu
 * entièrement ouvert, une interface d'administration derrière un mandataire — n'a rien à quoi
 * rapporter un identifiant. Le contrat ne pourrait répondre que faux, pour une raison qui n'est
 * pas un refus de droit : ce faux ferme au lieu d'ouvrir, il ne crée donc pas de faille, mais il
 * ferait mentir la seule chose que ce contrat sache faire.
 *
 * La faute est posée sur la définition plutôt que jugée ici, et c'est ce qui rend les deux
 * réponses possibles. {@see \Symfony\Component\DependencyInjection\Compiler\DefinitionErrorExceptionPass}
 * ne se déclenche qu'après {@see \Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass} :
 * une application qui n'injecte pas le contrat voit l'adaptateur retiré comme inutilisé et
 * compile sans un mot, une application qui l'injecte s'arrête sur le message ci-dessous. Aucune
 * des deux n'a besoin que cette passe sache qui référence quoi — chercher les références à la
 * main raterait les locators, les abonnés de services et les fermetures, et un contrôle qui
 * rate en silence est celui que tout ce paquet refuse.
 *
 * Elle ne nomme aucun adaptateur : elle part de l'alias du contrat et traite ce qu'il désigne.
 * C'est ce que la frontière entre les couches impose — une passe ne connaît que `Contract` —
 * et c'est aussi plus juste, puisqu'une application qui aurait substitué son propre adaptateur
 * reçoit le même traitement.
 *
 * Elle vit ici et non dans {@see \ArnaudMoncondhuy\Authorization\AuthorizationBundle::loadExtension()}
 * parce que la réponse n'existe pas encore quand l'extension se charge : c'est SecurityExtension
 * qui déclare les fournisseurs, et l'ordre de chargement des extensions ne se commande pas.
 */
final readonly class RefuseUserAuthorizerWithoutProviderPass implements CompilerPassInterface
{
    /**
     * Le service que SecurityExtension pose dans les deux cas qui comptent : alias vers
     * l'unique fournisseur, `ChainUserProvider` quand il y en a plusieurs. Son absence signifie
     * donc zéro fournisseur, et rien d'autre.
     *
     * `UserProviderInterface` ne dirait pas la même chose : cet alias-là n'existe que s'il y en
     * a exactement un, et le citer rendait le paquet ininstallable dans toute application à
     * deux modèles de comptes.
     */
    public const string USER_PROVIDERS_SERVICE = 'security.user_providers';

    /**
     * Ce que la commande `authorization:doctor` lit pour dire si le contrat est branché, et par
     * quoi. Un paramètre plutôt que le service lui-même : l'injecter suffirait à le compter
     * utilisé, et la commande chargée de rapporter l'absence deviendrait la cause de l'échec.
     */
    public const string ON_BEHALF_PARAMETER = 'authorization.on_behalf';

    public function process(ContainerBuilder $container): void
    {
        // Absent quand l'application n'a pas de pare-feu : `config/security.php` n'est alors
        // pas importé, et il n'y a rien à juger.
        if (!$container->hasAlias(UserAuthorizer::class)) {
            return;
        }

        if ($container->has(self::USER_PROVIDERS_SERVICE)) {
            return;
        }

        $adapter = (string) $container->getAlias(UserAuthorizer::class);

        if (!$container->hasDefinition($adapter)) {
            return;
        }

        $container->setParameter(self::ON_BEHALF_PARAMETER, null);

        $definition = $container->getDefinition($adapter);

        // La référence au fournisseur est retirée avant d'être jugée invalide. Sans cela,
        // CheckExceptionOnInvalidReferenceBehaviorPass — qui passe quatre rangs plus tôt que
        // DefinitionErrorExceptionPass — s'arrêterait le premier sur « dependency on a
        // non-existent service security.user_providers », un message vrai qui n'apprend rien à
        // qui n'a pas lu ce fichier.
        //
        // L'argument est reconnu à ce qu'il désigne, pas à son rang : un adaptateur substitué
        // par l'application ne range pas forcément ses dépendances dans le même ordre. Ce nul
        // n'est jamais construit — la définition qui le porte est soit retirée comme
        // inutilisée, soit arrêtée par l'erreur posée ci-dessous.
        foreach ($definition->getArguments() as $index => $argument) {
            if ($argument instanceof Reference && self::USER_PROVIDERS_SERVICE === (string) $argument) {
                $definition->replaceArgument($index, null);
            }
        }

        $definition->addError(\sprintf(
            'Le contrat %s a été demandé, mais la configuration de sécurité ne déclare aucun fournisseur de comptes : '
            .'aucun identifiant ne peut être rapporté à un compte, et répondre « non » à toutes les questions ferait '
            .'passer une absence de réponse pour un refus de droit. Déclarer une entrée sous `security.providers` '
            .'rebranche le contrat ; ne plus injecter %s le fait disparaître sans erreur.',
            UserAuthorizer::class,
            UserAuthorizer::class,
        ));
    }
}
