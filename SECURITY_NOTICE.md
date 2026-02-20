# Security Notice - API Keys

## ⚠️ IMPORTANT: API Key Leak Fixed

Your API keys were previously hardcoded in `ai_config.php` and exposed in GitHub. This has been fixed.

## What Changed

1. **Removed hardcoded keys** from `ai_config.php`
2. **Created secure storage** in `ai_keys.php` (not tracked in git)
3. **Added to .gitignore** to prevent future leaks
4. **Web interface** to configure keys securely

## Immediate Actions Required

### 1. Revoke Exposed API Key
Your old Gemini API key was exposed: `AIzaSyCAH7X-x31enepbzVcOLS0laeN37a6zFbw`

**Revoke it immediately:**
1. Go to https://makersuite.google.com/app/apikey
2. Find the exposed key
3. Delete/revoke it
4. Generate a new key

### 2. Configure New Keys
1. Go to: `http://your-domain/ai_keys_setup.php`
2. Login as admin
3. Enter your new API keys
4. Select AI provider
5. Save configuration

### 3. Clean Git History (Optional but Recommended)
The old key is still in your git history. To remove it:

```bash
# Install BFG Repo Cleaner
# Download from: https://rtyley.github.io/bfg-repo-cleaner/

# Clone a fresh copy
git clone --mirror https://github.com/yourusername/yourrepo.git

# Remove the exposed key from history
bfg --replace-text passwords.txt yourrepo.git

# Force push (WARNING: This rewrites history)
cd yourrepo.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push --force
```

Or use GitHub's guide: https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

## New Security Features

### Files Not Tracked in Git
- `ai_keys.php` - Contains your API keys
- `config.php` - Contains database credentials

### Files Tracked (Safe)
- `ai_config.php` - No sensitive data, just configuration
- `ai_keys.example.php` - Template file with placeholders
- `.gitignore` - Excludes sensitive files

### Access Control
- Only **admin users** can configure API keys
- Keys stored server-side only
- Never sent to client browser
- Protected by session authentication

## For Team Members

Each installation needs its own API keys:

1. Copy `ai_keys.example.php` to `ai_keys.php`
2. Add your own API keys
3. Or use the web interface: `/ai_keys_setup.php`

## Provider Options

### Mock (Default)
- No API key needed
- Instant results
- Good for testing
- Free

### Google Gemini (Recommended)
- Free tier: 60 requests/minute
- Get key: https://makersuite.google.com/app/apikey
- Fast and accurate

### OpenAI (Paid)
- Charges per request
- Get key: https://platform.openai.com/api-keys
- High quality

## Monitoring

Check for unauthorized usage:
- Gemini: https://console.cloud.google.com/apis/dashboard
- OpenAI: https://platform.openai.com/usage

## Questions?

- Configuration: Visit `/ai_keys_setup.php`
- Issues: Check file permissions on `ai_keys.php`
- Support: Contact system administrator

---

**Last Updated:** 2024
**Status:** ✅ Secured
