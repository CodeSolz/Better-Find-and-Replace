#!/bin/bash

SOURCE_DIR=$(pwd)
DEV_DIR="$SOURCE_DIR/../real-time-auto-find-and-replace-github/dev"
PROD_DIR="$SOURCE_DIR/../real-time-auto-find-and-replace-github/prod"

# Default empty commit messages
MDEV=""
MPROD=""
RELEASE_VERSION=""
SKIP_PROD=true
SKIP_RELEASE=true

# === Parse Arguments ===
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --mdev)
            MDEV="$2"
            shift 2
            ;;
        --mprod)
            MPROD="$2"
            SKIP_PROD=false
            shift 2
            ;;
        --release)
            RELEASE_VERSION="$2"
            SKIP_RELEASE=false
            shift 2
            ;;
        * )
            echo "❌ Unknown parameter: $1"
            echo "Usage: bash git-push.sh --mdev \"message for dev\" [--mprod \"message for prod\"] [--release \"1.3.8\"]"
            exit 1
            ;;
    esac
done

# === Validate DEV commit message ===
if [ -z "$MDEV" ]; then
    echo "⚠️ Error: Missing --mdev commit message"
    exit 1
fi

#####################
### Push to Repo A (DEV)
#####################
echo "🚀 Updating DEV repo (with src)..."

if [ ! -d "$DEV_DIR/.git" ]; then
    echo "❌ Dev repo not found! Please clone it to: $DEV_DIR"
    exit 1
fi

find "$DEV_DIR" -mindepth 1 -not -path "$DEV_DIR/.git*" -exec rm -rf {} +
shopt -s dotglob
for file in "$SOURCE_DIR"/*; do
    fname=$(basename "$file")
    if [[ "$fname" != "node_modules" && "$fname" != "vendor" && "$fname" != ".git" ]]; then
        cp -r "$file" "$DEV_DIR"
    fi
done

cd "$DEV_DIR" || exit
git checkout master || git checkout -b master
git pull origin master --rebase || echo "⚠️ Could not rebase, please check conflicts."

if [ -n "$(git status --porcelain)" ]; then
    git add .
    git commit -m "$MDEV"
    git push origin master
else
    echo "✅ No changes to push in DEV repo."
fi

#####################
### Optional: Push to Repo B (PROD)
#####################
if [ "$SKIP_PROD" = false ]; then
    echo "🚀 Updating PROD repo (cleaned version)..."

    if [ ! -d "$PROD_DIR/.git" ]; then
        echo "❌ Prod repo not found! Please clone it to: $PROD_DIR"
        exit 1
    fi

    find "$PROD_DIR" -mindepth 1 -not -path "$PROD_DIR/.git*" -exec rm -rf {} +
    for file in "$SOURCE_DIR"/*; do
        fname=$(basename "$file")
        if [[ "$fname" != "docs" && "$fname" != "src" && "$fname" != "node_modules" && "$fname" != "vendor" && "$fname" != ".git" && "$fname" != "git-push.sh" && "$fname" != "package copy.json" && "$fname" != "webpack.config.js" ]]; then
            cp -r "$file" "$PROD_DIR"
        fi
    done

    cd "$PROD_DIR" || exit
    if [ -n "$(git status --porcelain)" ]; then
        git add .
        git commit -m "$MPROD"
        git push origin master
    else
        echo "✅ No changes to push in PROD repo."
    fi
fi

#####################
### Optional: Tag & Release
#####################
if [ "$SKIP_RELEASE" = false ]; then
    echo "🚀 Preparing GitHub release for version $RELEASE_VERSION..."
    TAG="v$RELEASE_VERSION"

    # Run composer build
    echo "🔧 Running Composer build before tagging..."
    composer install --no-dev
    composer run build

    if git rev-parse "$TAG" >/dev/null 2>&1; then
        echo "⚠️ Git tag '$TAG' already exists locally. Skipping tag creation."
    else
        git tag "$TAG"
        echo "🏷️ Created local tag: $TAG"
    fi

    echo "📤 Pushing tag $TAG to origin..."
    git push origin "$TAG"
    echo "✅ Tag $TAG pushed. GitHub Actions will now create the release automatically."
else
    echo "ℹ️ No --release flag provided. Skipping tagging."
fi

# Return to the original directory
cd "$SOURCE_DIR"
echo "✅ DEV push complete."
