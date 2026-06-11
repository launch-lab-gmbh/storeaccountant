# Export Download Protection

StoreAccountant serves generated export archives through a frontend endpoint
identified by a random per-export download token. The token is not a password;
it only locates the export record. Each export run snapshots the effective
download password at creation time so later global or configuration password
changes do not change access to already-created exports.

Download passwords are stored in two forms:

- A reversible encrypted value for authorized backend reveal screens.
- A verification hash used by frontend password checks.

The global password is generated during plugin activation or the first settings
initialization. Export configurations store their own download password snapshot.
When the configuration password field is submitted empty, StoreAccountant stores
the current global password on the configuration. Export runs snapshot the
configuration password. Quick exports also accept a submitted download password;
when the quick export password field is empty, the export snapshots the current
global password directly.

Reversible encryption uses a provider chain:

1. Sodium with `sodium_crypto_secretbox()` when available.
2. OpenSSL AES-256-GCM when Sodium is unavailable.
3. Unavailable state when neither provider exists.

Encryption keys are derived from WordPress salts plus the
`storeaccountant_download_passwords` context. This keeps stored values bound to
the WordPress installation. Rotating salts invalidates password reveal for
previously encrypted values, but frontend verification can still use the
separate password hash. StoreAccountant does not currently provide automatic
key rotation.

The permission action for revealing stored download passwords is
`settings.view_download_passwords`, backed by the
`storeaccountant_view_download_passwords` capability. Download password reveal
is never required for frontend checks.

If neither Sodium nor OpenSSL is available, StoreAccountant must not silently
create public downloads without password protection. Password fields and reveal
controls are disabled, and export creation is blocked when a password snapshot
cannot be established.
