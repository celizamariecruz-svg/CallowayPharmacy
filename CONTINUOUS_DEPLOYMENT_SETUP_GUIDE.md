# Continuous Deployment Setup Guide (GitHub + Azure App Service)

Use this guide to switch from manual file upload to a proper Git-based workflow for continuous deployment.

---

## What this guide fixes

- Stops manual drag-and-drop uploads as your main deployment method.
- Pushes your latest local project code to GitHub correctly.
- Connects Azure App Service to your GitHub branch for automatic deployments.

---

## Before you start

1. Open PowerShell, then go to your project root (do **not** type the path alone):

   ```powershell
   Set-Location "C:\xampp\htdocs\CALLOWAYBACKUP1"
   ```

   (Equivalent short command: `cd "C:\xampp\htdocs\CALLOWAYBACKUP1"`)

2. Confirm Git is installed:

   ```powershell
   git --version
   ```

3. Confirm you can access GitHub in browser:

   `https://github.com/celizamariecruz-svg/CallowayPharmacy`

---

## Step 1: Prepare local repository

### 1.1 Check current status

```powershell
git status --short --branch
git remote -v
```

### 1.2 Ensure important files are ignored

You already have a `.gitignore`. Add these lines if missing (to avoid committing deployment artifacts):

```gitignore
_deploy_bundle/
deploy_ready_*.zip
_archive_dev/
__pycache__/
```

Then stage `.gitignore` if you changed it:

```powershell
git add .gitignore
```

### 1.3 Remove accidentally tracked deployment artifacts (if any)

Run these commands safely (they will only affect git index if files are tracked):

```powershell
git rm -r --cached _deploy_bundle 2>$null
git rm --cached deploy_ready_*.zip 2>$null
```

---

## Step 2: Commit your latest local code

### 2.1 Configure git identity (only once per machine)

```powershell
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

### 2.2 Stage and commit everything you want deployed

```powershell
git add -A
git commit -m "chore: prepare latest code for Azure continuous deployment"
```

If commit says "nothing to commit", continue to next step.

---

## Step 3: Point local repo to your GitHub repo

You currently may have `origin` set to a different repository. Replace it with your target repo:

```powershell
git remote remove origin
git remote add origin https://github.com/celizamariecruz-svg/CallowayPharmacy.git
git remote -v
```

Expected output should show:

- `origin  https://github.com/celizamariecruz-svg/CallowayPharmacy.git (fetch)`
- `origin  https://github.com/celizamariecruz-svg/CallowayPharmacy.git (push)`

---

## Step 4: Push code to GitHub

Because you manually uploaded files earlier, remote history may not match local history.

### Option A (recommended here): replace remote with your local project snapshot

Use this when remote only has placeholder/upload commits and you want local code to become source of truth:

```powershell
git branch -M main
git push -u origin main --force
```

### Option B (no force): merge remote and local histories

Use this if you must keep remote commit history:

```powershell
git branch -M main
git pull origin main --allow-unrelated-histories
git push -u origin main
```

If conflicts appear, resolve them, then:

```powershell
git add -A
git commit -m "merge: combine local and remote histories"
git push -u origin main
```

---

## Step 5: Verify GitHub repository content

In GitHub, confirm:

- You see your PHP project files in root.
- `deploy_ready_*.zip` is not in source root (recommended).
- Latest commit message matches what you pushed.

---

## Step 6: Enable Azure continuous deployment

In Azure Portal (App Service > Deployment Center):

1. Go to **Deployment** tab.
2. Set **Continuous deployment** to **Enable**.
3. Click **Authorize** under GitHub account.
4. Pick:
   - Organization: your GitHub account/org
   - Repository: `CallowayPharmacy`
   - Branch: `main`
5. Review generated workflow.
6. Click **Save** or **Review + create**.

Azure will create a GitHub Actions workflow in your repository (usually under `.github/workflows/`).

---

## Step 7: First deployment test

1. Make a tiny code change locally (example: add one comment in a non-critical file).
2. Commit and push:

```powershell
git add -A
git commit -m "test: trigger Azure CD"
git push
```

3. Check GitHub Actions tab for build/deploy status.
4. Check App Service Deployment Center logs.
5. Open your Azure site and verify the change is live.

---

## Daily workflow after setup

From now on, do this only:

```powershell
git add -A
git commit -m "describe your change"
git push
```

Azure deploys automatically from the selected branch.

---

## Troubleshooting

### Git asks for authentication repeatedly

- Use GitHub login prompt and complete authentication.
- If needed, use Git Credential Manager (default on modern Git for Windows).

### Push rejected (non-fast-forward)

- If this is expected due to old manual-upload history, use the Step 4 Option A force push once.

### Azure deployment fails

- Open GitHub Actions logs and inspect failing step.
- Verify App Service startup/runtime settings (PHP version, startup command, environment variables).
- Confirm secrets/config values are set in App Service Configuration (not hardcoded in repo).

---

## Important notes

- Do not upload ZIP artifacts to the source repository for continuous deployment.
- Keep `.env`, keys, and credentials out of git.
- Use App Service Configuration / Key Vault for secrets.

---

If you want, next step is to create a second short file named `AZURE_CD_QUICK_CHECKLIST.md` with only a one-page checklist for defense-day execution.