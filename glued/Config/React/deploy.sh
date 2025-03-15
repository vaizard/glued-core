#!/usr/bin/env bash
# TODO Git clone mrdati. nedelat, vyhodit do separe mikrosluzby
pushd ../data
git clone --depth=1 https://gitlab.com/vaizard/glued-react
pushd glued-react
git config --global --add safe.directory $(pwd)
git stash
git pull
ln -s ../../glued-core/.env ./.env
export $(echo $(cat .env | sed 's/#.*//g'| xargs) | envsubst);
export $(echo $(cat .env | sed 's/#.*//g'| xargs) | envsubst);
corepack enable
yarn install
echo "REACT_APP_ENDPOINT=\"${REACT_APP_ENDPOINT}\""
echo "REACT_APP_AUTH_TOKEN_ENDPOINT=\"${REACT_APP_AUTH_TOKEN_ENDPOINT}\""
echo "REACT_APP_AUTH_CLIENT_ID=\"${REACT_APP_AUTH_CLIENT_ID}\""
echo "REACT_APP_AUTH_ENDSESSION_ENDPOINT=\"${REACT_APP_AUTH_ENDSESSION_ENDPOINT}\""
echo "REACT_APP_AUTH_ENDPOINT=\"${REACT_APP_AUTH_ENDPOINT}\""
echo "CONFIG_NAME=\"${CONFIG_NAME-"dev"}\" npm run build"
CONFIG_NAME=${CONFIG_NAME} npm run build
cp -r ./build/* ../../glued-core/public
popd
popd