# Monter le paquet dans une application

Les six gestes, dans l'ordre où on les fait. Chacun se vérifie avant de passer au suivant : une
étape qu'on croit faite et qui ne l'est pas se découvre bien plus tard, et bien plus cher.

| # | Le geste | Ce qui le vérifie |
|---|---|---|
| 1 | Déclarer les droits | `authorization:permissions` les liste |
| 2 | Écrire le cas d'usage | `cache:clear` refuse s'il ne déclare rien |
| 3 | Écrire le voter | `authorization:doctor` refuse un droit sans juge |
| 4 | Lister les droits pour les accorder | l'écran affiche ce que le code exige |
| 5 | Brancher le contrôle des corps de méthode | il attrape une garde mise en commentaire |
| 6 | Examiner l'installation | le docteur rend un code de sortie |

Ce qu'aucune de ces étapes ne couvre est écrit dans [ce qui reste au
projet](ce-qui-reste-au-projet.md), et se lit **avant** de commencer.

## 1. Déclarer les droits, une énumération par contexte métier

```php
namespace App\Domain\Invoice;

use ArnaudMoncondhuy\Authorization\Permission;

enum InvoicePermission: string implements Permission
{
    case View = 'invoice.view';
    case Finalize = 'invoice.finalize';
    case Backdate = 'invoice.backdate';

    public function id(): string
    {
        return $this->value;
    }
}
```

**Une énumération, et rien d'autre.** Le contrat étend `\UnitEnum` : une classe ordinaire qui
l'implémenterait est arrêtée par PHP à la ligne où elle se déclare. Un droit est un nom, pas
un état — et c'est ce qui permet au conteneur compilé de conserver l'inventaire tel quel.

**Préfixez l'identité par son contexte.** Deux contextes qui choisiraient `view` désigneraient
le même droit, et le partageraient — le paquet refuse de compiler dans ce cas, mais autant ne
pas s'y exposer.

**L'identité est stable une fois écrite.** C'est elle qu'un compte se voit accorder, donc elle
survit en base à la classe qui la déclare : la renommer sans reprendre les droits déjà
accordés les révoque en silence.

## 2. Écrire le cas d'usage

Voir l'exemple en tête. Trois règles de forme :

- `#[RequiresPermission]` au niveau **classe**, autant de fois qu'il exige de droits ;
- une seule méthode publique, `__invoke()` ;
- `$this->access->require(…)` **en tête**, avant toute lecture et toute écriture.

Le troisième point n'est pas cosmétique : un refus qui arrive après une lecture a déjà laissé
fuir ce qu'il refusait.

Le contrat porte une seconde méthode, `can()`, qui **répond au lieu d'arrêter** :

```php
{% if access.can(constant('App\\Domain\\Invoice\\InvoicePermission::Finalize')) %}
```

Elle sert à une surface qui n'affiche que ce qui marchera — un bouton, une entrée de menu, un
outil dans une liste. Elle ne remplace jamais `require()` : ce que la surface cache, le verbe
doit continuer de le refuser, sinon la porte est ouverte à qui devine l'adresse. Un droit
déclaré que le corps se contente de tester est d'ailleurs signalé comme jamais réclamé.

## 3. Écrire le voter — c'est votre part

Le paquet transforme chaque droit en attribut soumis au contrôle d'accès de Symfony. À vous de
dire qui l'obtient.

Vous n'êtes pas tenu de partir de la page blanche : `authorization:doctor` écrit le squelette
du voter qui manque, avec les identités dedans et la garde en place — voir l'étape 6. Ce qu'il
laisse à écrire est la seule chose qu'il ne peut pas savoir : qui détient le droit.

```php
namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PermissionVoter extends Voter
{
    public function __construct(private readonly GrantRepository $grants)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->grants->grants($user, $attribute);
    }
}
```

Le cinquième paramètre, `?Vote $vote = null`, est celui de Symfony 8 : l'omettre fait échouer
la compatibilité de signature.

`supports()` est le seul endroit délicat : il doit reconnaître vos identités **et rien
d'autre**, sans quoi le voter se prononcera sur des attributs qui ne le regardent pas
(`ROLE_USER`, `IS_AUTHENTICATED_FULLY`…). Une convention de préfixe explicite vaut mieux que le
`str_contains` ci-dessus.

**Un voter qui s'abstient faute d'utilisateur ferme le verbe à toute surface sans session** —
console, worker, tâche planifiée. Si vous en avez, préférez refuser explicitement plutôt que
vous abstenir, et voyez la recette « Une surface sans utilisateur ».

## 4. Lister les droits pour les accorder

```php
final class RightsController
{
    public function __invoke(PermissionCatalog $catalog): Response
    {
        // Tous les droits que le code exige, une seule fois chacun, triés par identité.
        $catalog->ids();

        // Un droit stocké que plus aucun cas d'usage n'exige : une case devenue sans effet.
        $catalog->isRequired('invoice.renamed');
    }
}
```

L'inventaire vient des mêmes déclarations que le contrôle : un droit ajouté au code ne peut
pas manquer à la liste, et la liste ne peut pas proposer un droit que rien n'exige.

## 5. Brancher le contrôle qui lit les corps de méthode

```php
use ArnaudMoncondhuy\Authorization\Testing\PermissionUsage;

final class PermissionUsageTest extends TestCase
{
    public function testNoUseCaseBreaksItsContract(): void
    {
        self::assertSame([], PermissionUsage::violationsUnder(__DIR__.'/../src', 'App\\'));
    }
}
```

Il vérifie les deux sens : un droit **déclaré et jamais réclamé** fait apparaître une case que
rien n'applique ; un droit **réclamé et jamais déclaré** n'entre dans aucun inventaire, ne peut
être accordé à personne, et ferme le verbe pour tout le monde — administrateur compris.

Il attrape aussi la brèche signalée plus haut : **une classe qui réclame un droit sans être un
cas d'usage**. Les trois premiers refus de compilation ne la voient pas, puisqu'elle n'implémente pas
`UseCase`.

**La forme qu'il reconnaît, et elle contraint votre écriture :** il cherche
`->require(Enumeration::Case)` **écrit en toutes lettres** dans le corps de `__invoke()`.

```php
$this->access->require(InvoicePermission::Finalize);   // vu

$permission = InvoicePermission::Finalize;
$this->access->require($permission);                   // signalé : « réclame un droit
                                                       // par une valeur », illisible
```

Ce n'est pas une négligence : c'est ce qui rend le contrôle sûr. Chercher le nom du droit
plutôt que l'appel laisserait passer un `can()` qui teste sans s'y tenir, puisque les deux
s'écrivent avec les mêmes caractères à un mot près. Écrivez donc le cas en clair — c'est aussi
ce qui rend le corps lisible.

Le corps est lu **débarrassé de ce qui ne s'exécute pas** : commentaires, chaînes littérales,
heredocs. Une garde mise en commentaire le temps d'un débogage n'est donc pas une garde, et
elle est signalée comme telle — sans quoi le contrôle ouvrirait en croyant fermer.

Un droit choisi par une **valeur** est signalé pour ce qu'il est, et non accusé de n'être
jamais réclamé : le geste est bien gouverné, c'est le rapprochement qui devient impossible.

**Ses autres limites, écrites plutôt que tues :** il ne voit pas une énumération importée sous
un autre nom, et **il ne vérifie pas que `require()` vient avant la première lecture**. Un
refus posé après coup a déjà laissé fuir ce qu'il refusait, et aucune lecture de texte ne sait
distinguer une lecture d'un calcul — c'est une règle qui reste à votre charge.

## 6. Examiner l'installation

```bash
php bin/console authorization:permissions   # ce que le code exige
php bin/console authorization:doctor        # ce qui manque pour que ça marche
```

Le docteur cherche ce qu'aucun autre contrôle ne voit : **un droit qu'aucun voter ne prend en
charge**. Le code l'exige, l'inventaire le propose, l'écran d'attribution permet de le cocher —
et il est refusé à tout le monde, administrateur compris, sans qu'aucune erreur ne soit levée.

**Il écrit le voter qui manque**, un par contexte, et rien qu'il ne sache : les identités sans
juge et elles seules — reprendre l'énumération entière donnerait un second juge à celles qui en
ont déjà un —, la garde qui refuse un jeton sans utilisateur, et une règle de décision laissée
vide, qui refuse. Le nom et l'espace de noms sont une proposition ; le paquet ne connaît ni
votre arborescence ni vos conventions.

Le squelette est **affiché, jamais écrit sur disque**, et il n'accorde rien. Qui détient un
droit reste une décision, et une décision ne se devine pas : un correctif automatique qui se
tromperait ouvrirait ou fermerait un accès en silence, ce que cette commande existe précisément
pour rendre visible.

Il rend un code de sortie, donc une routine qualité peut s'appuyer dessus. Sur une application
sans droit déclaré, il dit qu'il n'y a rien à examiner plutôt que de rendre un vert franc.

Il **compte** les juges plutôt que de s'arrêter au premier : un droit jugé par plusieurs voters
est signalé, parce que sous la stratégie `affirmative` le recouvrement élargit les droits au
lieu de les restreindre. Ce n'est pas toujours une faute, d'où un signalement par défaut et
`--strict` pour en faire un échec.

**Il exécute les voters pour de vrai.** Si l'un des vôtres lit la base, la commande réclame une
base et un schéma à jour — c'est ce qui rend sa réponse fiable, et ce qui la fait échouer
bruyamment avant une migration.

**Sa mesure est indirecte, et il faut le savoir.** Il conclut qu'un droit a un juge si un voter
ne s'abstient pas dessus, interrogé sans utilisateur connecté. Sur un voter bâti sur la classe
abstraite de Symfony, c'est exact — l'abstention y est la réponse quand `supports()` refuse.
Un voter qui s'abstient pour une autre raison, l'absence d'utilisateur par exemple, serait
compté absent à tort.

**Ce qu'il ne voit pas, et que rien ne voit :** une identité **renommée**. Le code compile, les
tests passent, le docteur est vert — et tous ceux qui détenaient l'ancienne l'ont perdue en
silence. C'est la seule chaîne du dispositif qui survive en base à la classe qui la déclare, et
la comparer à ce qui est accordé est un contrôle que seule votre application peut faire, avec
`PermissionCatalog::isRequired()`.

Ce qu'il ne voit pas non plus : un droit jugé par le mauvais modèle. Si l'écran laisse le
cocher sur un rôle quand seul un groupe l'accorde, le droit a bien un juge — il est seulement
accordé au mauvais endroit.

## La seule clé de configuration

Les six gestes tiennent sans écrire une ligne de configuration : le paquet se câble sur ce que
l'application déclare déjà. Une clé existe pourtant, et elle ne sert qu'à **restreindre**.

`UserAuthorizer` — ce qu'un autre que l'appelant a le droit de faire — désigne un compte par son
identifiant, et le charge par le fournisseur de comptes de l'application. Il interroge
`security.user_providers`, le service que Symfony pose **dès qu'il existe un fournisseur** :
un alias vers l'unique, un `ChainUserProvider` au-delà. Un compte est donc trouvé quel que soit
l'annuaire où il vit — une table, un annuaire LDAP, les deux.

Quand la chaîne entière est de trop — des comptes de service qu'aucune notification ne doit
atteindre, deux annuaires qui peuvent porter le même identifiant, un annuaire distant qu'on ne
veut pas interroger pour rien — nommez celui où chercher :

```yaml
# config/packages/authorization.yaml
authorization:
    user_provider: security.user.provider.concrete.app_users
```

C'est le nom du **service**, et non celui de l'entrée : Symfony le fabrique en préfixant
`security.user.provider.concrete.` au nom écrit sous `security.providers`, en minuscules. Un nom
qui ne désigne aucun service arrête la compilation sur le message de Symfony, qui le cite tel
qu'il a été écrit — mais le jour seulement où un service de l'application injecte le contrat,
comme toute référence que le conteneur retire tant que personne ne s'en sert
([ce qui casse](risques.md), § 16).

Ce que le geste change ne se voit qu'à un endroit — `authorization:doctor`, sur la ligne qui
suit le contrat :

```
Contrat  : ArnaudMoncondhuy\Authorization\Bridge\SecurityAuthorizer
Tiers    : ArnaudMoncondhuy\Authorization\Bridge\SecurityUserAuthorizer
Annuaire : security.user.provider.concrete.app_users
```

Sans la clé, cette ligne affiche `security.user_providers` : la chaîne entière. La restriction ne
change rien à la compilation, et tout aux comptes qui seront trouvés — un identifiant que
l'annuaire nommé ne porte pas rend `false`, comme un identifiant qui n'existe nulle part.

---
