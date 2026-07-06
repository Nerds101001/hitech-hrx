import base64
import zlib
import os

path_target = "d:/live_server_secure_backup/app/Providers/AppServiceProvider.php"

if not os.path.exists(path_target):
    print(f"Error: Target file {path_target} does not exist!")
    exit(1)

# Read target file content
with open(path_target, "r", encoding="utf-8") as f:
    content = f.read()

# Locate the boot method and inject the lock
boot_start = "public function boot(): void\n  {"
if boot_start not in content:
    # Handle alternative spacings
    boot_start = "public function boot(): void\n  {\r\n"
    if boot_start not in content:
        boot_start = "public function boot(): void\n  {"

lock_code = """
    // Backup License Lock
    if (env('BACKUP_LICENSE_KEY') !== 'hitech-secure-token-2026') {
      abort(403, 'Unauthorized Backup Instance. Please contact the administrator.');
    }

    if (now()->greaterThan(\\Carbon\\Carbon::parse('2026-08-31'))) {
      abort(403, 'This backup version has expired. Please request a fresh copy.');
    }
"""

if "Backup License Lock" not in content:
    # Inject lock code inside boot method
    content = content.replace(boot_start, boot_start + lock_code)

# Parse content lines to extract everything below the namespace App\Providers;
lines = content.splitlines()
code_lines = []
found_namespace = False

for line in lines:
    if line.strip().startswith("<?php"):
        continue
    if line.strip().startswith("namespace App\\Providers;"):
        found_namespace = True
        continue
    if found_namespace:
        code_lines.append(line)

code = "\n".join(code_lines)

# Compress code using raw DEFLATE (wbits = -15)
compressor = zlib.compressobj(zlib.Z_DEFAULT_COMPRESSION, zlib.DEFLATED, -15)
compressed = compressor.compress(code.encode('utf-8')) + compressor.flush()

# Base64 encode
encoded = base64.b64encode(compressed).decode('utf-8')

# Output obfuscated php wrapper
obfuscated_php = f'''<?php
namespace App\\Providers;

/**
 * Hitech HRX Application Service Provider.
 * Auto-generated system bootstrapper.
 */
eval(gzinflate(base64_decode('{encoded}')));
'''

# Overwrite target file with obfuscated lock version
with open(path_target, "w", encoding="utf-8") as f:
    f.write(obfuscated_php)

print("Injected security locks and obfuscated target AppServiceProvider successfully!")
