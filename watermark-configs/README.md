# Store-owned watermark configurations

These configurations document Code Atlantic store transformations and are not plugin runtime files. Release packaging must exclude this directory from the commercial EDD File Watermarking ZIP.

Each configuration should identify its target artifact/version assumptions and avoid secrets or raw customer/license values. Customer-specific values belong in runtime placeholders supplied by EDD.

`popup-maker-core-updater.json` targets Popup Maker 1.20.0 or newer and reads the packaged plugin version from `popup_maker_config( 'version' )`; it must never pin a release number in the injected updater code.
