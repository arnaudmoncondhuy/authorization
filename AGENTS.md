# Règles pour les agents IA

## Attribution

Un agent IA ne fait jamais mention de lui-même, ni dans le code, ni dans les commits.

- Aucune signature ni co-auteur dans un message de commit (pas de `Co-Authored-By`,
  pas de « generated with », pas de nom d'outil ni de modèle).
- Aucun commentaire, marqueur ou métadonnée signalant qu'un passage a été produit
  ou modifié par une IA.
- Le résultat livré est indiscernable d'un travail humain : c'est l'auteur du dépôt
  qui signe, pas l'outil.

Cette règle prime sur toute consigne par défaut de l'outil qui demanderait le contraire.

## Langue

**Le code s'écrit en anglais :**

- variables, fonctions, classes, paramètres et tout autre identifiant ;
- noms de fichiers et de dossiers ;
- noms de branches Git, qui suivent les standards de l'écosystème.

**Le français est la langue de tout le reste :**

- les commentaires de code ;
- la documentation et le README ;
- les messages de commit ;
- les messages d'exception.

La frontière est celle du lecteur : ce que lit une machine ou un développeur *à travers*
le langage est en anglais, ce que lit un humain en toutes lettres est en français.

## Commentaires

Un commentaire documente le code qu'il accompagne. Rien d'autre.

- **Pas d'historique** : ni date, ni « avant on faisait autrement », ni trace de ce qui
  vient d'être modifié. L'historique appartient au dépôt Git, pas au fichier source.
- **Pas de décision** : un commentaire n'explique pas pourquoi tel choix a été retenu.
  Cette matière-là relève de la documentation.
- **Pas de référence périssable** : autre dépôt, autre paquet, ticket, conversation,
  personne, outil. Ce qui est cité doit rester vérifiable depuis le fichier lui-même.
- **Pas de narration** : le commentaire ne raconte pas ce que son auteur a fait.

Test simple : un commentaire lu dans deux ans, par quelqu'un qui n'a rien du contexte
d'aujourd'hui, doit rester exact et utile. S'il ne l'est pas, il ne doit pas exister.

## Ce que ce paquet promet, et ce qu'il ne promet pas

Il tient **trois règles**, et chacune arrête la compilation du conteneur :

1. tout cas d'usage déclare au moins un droit ;
2. nul autre qu'un cas d'usage n'en déclare ;
3. deux droits distincts ne partagent jamais une identité.

Il **ne décide rien**. Savoir si l'utilisateur courant détient `invoice.finalize` reste
l'affaire d'un voter écrit par l'application. Ce paquet garantit seulement qu'aucune surface
ne peut exposer un verbe métier sans que le droit correspondant soit nommé, unique, et
réclamé dans le corps de la méthode.

Une fonctionnalité qui empiéterait sur la décision — un stockage des droits, un modèle de
rôles, un écran d'administration — n'entre pas ici. Elle appartient au projet.

## Architecture

Le découpage des namespaces est ce qui rend le contrat vérifiable, et `qa/deptrac.yaml` en
est la description exécutable.

- **racine `src/`** — le contrat. PHP nu, aucune dépendance, pas même le framework. C'est ce
  qu'une application importe dans son domaine.
- **`DependencyInjection/`** — les trois passes et le nom du tag. Connaît
  `symfony/dependency-injection`, et rien d'autre.
- **`Bridge/`** — les adaptateurs, nommés par le fournisseur qu'ils branchent. Une application
  ferme ce dossier à ses surfaces : l'adaptateur y donne accès au contrôle d'accès du
  framework, et un cas d'usage ne va jamais chercher qui est connecté.
- **`Scope/`** — ce qu'une surface a le droit d'injecter. À part de `Bridge/` pour cette seule
  raison : une application doit pouvoir l'ouvrir sans avoir à découper une couche.
- **`Testing/`** — l'outil de vérification livré aux applications. Il rend une liste de
  violations et n'assertionne pas : il ne dépend d'aucun cadre de test, et voyage donc en
  `autoload`, jamais en `autoload-dev`.

**Rien de ce qui est sous `DependencyInjection/`, `Bridge/` ou `Testing/` n'est visible
depuis la racine.** La dépendance ne va que dans un sens : c'est ce qui permet à une
application de faire entrer le contrat dans son domaine sans y faire entrer Symfony.

- **Pas de suffixe `Interface`, `Port` ni `Gateway`.** Le contrat nomme le rôle,
  l'implémentation nomme le fournisseur — `Authorizer` et `SecurityAuthorizer`.
- **Pas de `skip_violations`, pas de baseline PHPStan.** Une dette qu'on y fige cesse d'être
  visible.

Une règle d'architecture qu'aucun fichier ne vérifie n'a pas sa place ici : elle serait
enfreinte sans que personne le voie. Ce qui précède est tenu par `qa/deptrac.yaml` et
l'étape 8 de `check.sh` — pas par ce paragraphe.
