// Override broken bosh/websocket URLs for local HTTP dev
config.bosh = '/http-bind';
delete config.websocket;
