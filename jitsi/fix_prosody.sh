#!/bin/bash
sed -i 's/--"bosh";/"bosh";/' /config/prosody.cfg.lua
sed -i 's/--"http_files";/"http_files";/' /config/prosody.cfg.lua
echo "Fixed modules. Checking:"
grep '"bosh"\|"http_files"' /config/prosody.cfg.lua
