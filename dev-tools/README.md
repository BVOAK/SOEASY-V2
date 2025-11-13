# 🛠️ Dev Tools - Scripts pour Claude AI

Ce dossier contient les scripts d'automatisation pour travailler avec Claude sur le projet SoEasy.

## 📋 Problème résolu

Claude AI tourne dans un environnement Linux isolé avec un proxy qui **bloque `git clone/pull`**. Ces scripts contournent le problème en utilisant l'API GitHub directement.

## 🚀 Scripts disponibles

### 1️⃣ `init-claude-workspace.sh`
**Usage:** Au début de chaque nouvelle conversation Claude

```bash
bash /home/claude/SOEASY-V2-main/dev-tools/init-claude-workspace.sh
```

**Ce qu'il fait:**
- ✅ Télécharge le projet depuis GitHub (si pas déjà présent)
- ✅ Vérifie et synchronise avec la dernière version
- ✅ Configure l'environnement de travail
- ✅ Affiche les infos utiles

### 2️⃣ `sync-repo-claude.sh`
**Usage:** Après chaque push GitHub pour récupérer les modifications

```bash
bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh
```

**Ce qu'il fait:**
- ✅ Vérifie le dernier commit sur GitHub via l'API
- ✅ Compare avec la version locale
- ✅ Télécharge la nouvelle version si nécessaire
- ✅ Fait un backup automatique de l'ancienne version
- ✅ Extrait et configure le nouveau code

## 📖 Workflow recommandé

### Nouvelle conversation Claude
```bash
# Dans le nouveau chat, demande à Claude:
"Exécute le script d'initialisation du projet SoEasy"

# Ou donne-lui directement:
bash /home/claude/SOEASY-V2-main/dev-tools/init-claude-workspace.sh
```

### Après avoir pushé des modifications
```bash
# Dis à Claude:
"Synchronise le repo GitHub"

# Ou:
bash /home/claude/SOEASY-V2-main/dev-tools/sync-repo-claude.sh
```

## 🔍 Fichiers techniques

### `.last_sync_sha`
Fichier caché contenant le SHA du dernier commit synchronisé. Permet de détecter automatiquement les mises à jour.

### Backups
Les anciennes versions sont automatiquement sauvegardées dans:
```
/home/claude/SOEASY-V2-main.backup.YYYYMMDD-HHMMSS/
```

Tu peux les supprimer avec:
```bash
rm -rf /home/claude/SOEASY-V2-main.backup.*
```

## ⚠️ Limitations connues

- ❌ **`git clone/pull/fetch` ne fonctionnent pas** à cause du proxy Anthropic
- ✅ **L'API GitHub fonctionne** via HTTPS avec le proxy
- ✅ **`wget/curl` fonctionnent** pour télécharger les archives
- ⏱️ Le token JWT du proxy expire après ~4h (mais se renouvelle automatiquement)

## 🆘 Dépannage

### "unable to access GitHub"
Le proxy JWT a peut-être expiré. Relance simplement le script, le proxy se renouvelle automatiquement.

### "Repo déjà à jour"
Normal ! Le script détecte qu'il n'y a pas de nouveaux commits. Si tu es sûr d'avoir pushé, vérifie sur GitHub que ton commit est bien sur la branche `main`.

### "functions.php introuvable"
Erreur d'extraction. Supprime le dossier et relance l'initialisation:
```bash
rm -rf /home/claude/SOEASY-V2-main
bash /home/claude/SOEASY-V2-main/dev-tools/init-claude-workspace.sh
```

## 📝 Notes pour les développeurs

Ces scripts sont spécifiques à l'environnement Claude AI et ne sont **pas nécessaires** pour le développement local normal avec Git.

Pour le dev local, utilisez le workflow Git classique:
```bash
git clone https://github.com/BVOAK/SOEASY-V2.git
git pull origin main
# etc.
```

---

**Créé par:** Fred @ BVOAK  
**Dernière mise à jour:** 2025-11-13
