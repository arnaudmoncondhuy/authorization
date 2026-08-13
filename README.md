# arnaudmoncondhuy/authorization

Un droit se déclare **une fois**, sur le verbe métier. Le conteneur refuse de compiler sinon,
et l'inventaire des droits se dérive du code.

```php
#[RequiresPermission(InvoicePermission::Finalize)]
#[RequiresPermission(InvoicePermission::Backdate)]
final readonly class FinalizeInvoiceUseCase implements UseCase
{
    public function __construct(private Authorizer $access)
    {
    }

    public function __invoke(string $number, ?\DateTimeImmutable $backdatedTo = null): void
    {
        $this->access->require(InvoicePermission::Finalize);

        if (null !== $backdatedTo) {
            $this->access->require(InvoicePermission::Backdate);
        }
    }
}
```

Une page, une API, un outil pour IA, une commande : toutes appellent ce verbe, aucune ne
redéclare ses droits. C'est ce qui fait qu'une capacité ne peut pas diverger d'une surface à
l'autre — il n'y a qu'un endroit où elle est écrite.

## Ce que le paquet garantit

Quatre règles, et chacune **arrête la compilation du conteneur**. Pas un contrôle
d'intégration continue qu'on peut contourner : l'application ne démarre pas, y compris sur le
poste de qui a écrit la faute.

| Règle | Ce qu'elle empêche |
|---|---|
| Tout cas d'usage déclare au moins un droit | un verbe qui s'exécute sans arbitrage, et qu'on ne peut accorder à personne |
| Nul autre qu'un cas d'usage n'en déclare | une surface qui durcit son côté sans toucher au verbe, et l'inverse |
| Deux droits distincts ne partagent jamais une identité | accorder un droit dans un contexte l'accorder dans l'autre — la collision se juge sur la valeur, donc entre deux cas d'une même énumération autant qu'entre deux énumérations |
| Toute porte d'entrée appelle un verbe métier | une surface qui agit sans traverser aucun contrôle de droit — c'est par là qu'un inconnu sans compte peut faire ce qu'il veut |

**Les trois premières ne jugent que ce qui implémente `UseCase`.** Une classe qui oublie
l'interface leur échappe entièrement, tout en pouvant réclamer des droits. Le langage ne sait
pas l'empêcher ; `PermissionUsage` le rattrape en test, et c'est écrit ici plutôt que passé sous silence, parce
que c'est la seule brèche du dispositif.

Deux contrôles ne peuvent pas arrêter la compilation et se jouent donc ailleurs :

- rapprocher ce que l'attribut **déclare** de ce que le corps **réclame** demande de lire un
  corps de méthode — `PermissionUsage`, en test ;
- savoir si un droit trouve **quelqu'un pour en juger** demande d'interroger les voters
  installés — `authorization:doctor`, en ligne de commande.

## Ce que le paquet ne fait pas

**Il ne décide rien.** Savoir si l'utilisateur courant détient `invoice.finalize` reste
l'affaire d'un voter que vous écrivez. Ce paquet garantit seulement qu'aucune surface ne peut
exposer un verbe métier sans que le droit correspondant soit nommé, unique, et réclamé dans le
corps de la méthode.

Il ne fournit ni stockage des droits, ni modèle de rôles, ni écran d'administration. Le modèle
appartient au projet : rôles, groupes, droits par compte, ou les trois à la fois — le paquet
n'en sait rien et n'a pas à en savoir.

## Installation

```bash
composer require arnaudmoncondhuy/authorization
```

Aucune recette Flex : le bundle s'enregistre à la main dans `config/bundles.php`.

```php
return [
    // …
    ArnaudMoncondhuy\Authorization\AuthorizationBundle::class => ['all' => true],
];
```

## Où lire quoi

| Document | Ce qu'il répond |
|---|---|
| [Monter le paquet](docs/montage.md) | les six gestes, dans l'ordre, et ce qui vérifie chacun |
| [Recettes](docs/recettes.md) | une console sans utilisateur, la mise en page d'un refus, les libellés, deux modèles de droits, l'objet interdit… |
| [Ce qui reste au projet](docs/ce-qui-reste-au-projet.md) | les treize règles que le paquet ne tiendra pas pour vous — **à lire avant d'adopter** |
| [Ce qui casse](docs/risques.md) | quinze façons de se tromper, et pour chacune si le docteur pourrait la voir |

Ces quatre documents ne se recouvrent pas : une question, un domicile. Ce qui se répète finit
par diverger.

## Dépendances

**Symfony `^7.3 || ^8.1`**, et donc la **7.4 LTS**, où tourne la majeure partie du parc en
production. Les deux branches sont jouées par la pipeline : la routine complète sur la plus
haute, la suite de tests sur la 7.4. Une compatibilité qu'on annonce sans la jouer n'est pas
une compatibilité.

| Composant | Pourquoi |
|---|---|
| `symfony/dependency-injection` | les quatre passes |
| `symfony/config` | le chargement de la configuration du bundle |
| `symfony/security-core` | l'adaptateur qui soumet l'identité au contrôle d'accès |
| `symfony/event-dispatcher` | l'écouteur qui traduit un refus |
| `symfony/http-kernel` | la classe de bundle, et l'écouteur qui traduit un refus en 403 |
| `symfony/console` | *suggéré* — sans lui, les deux commandes n'existent pas |

Le **contrat** — `Permission`, `UseCase`, `RequiresPermission`, `Authorizer`,
`MissingPermission`, `PermissionCatalog`, `UserAuthorizer` — est du PHP nu, sans une seule dépendance. C'est ce
qui permet de le citer depuis un domaine pur. La routine qualité le vérifie à chaque
exécution, imports et noms qualifiés compris.

## Version

`0.x` : aucune promesse de compatibilité. La surface publique peut bouger d'une version mineure
à l'autre tant que `1.0` n'est pas sorti.

## Licence

MIT.
