#!/usr/bin/env bash
# TODO Git clone mrdati. nedelat, vyhodit do separe mikrosluzby
pushd ../data
git clone --depth=1 https://github.com/vaizard/glued-react
pushd glued-react
git pull
cp -r ../../glued-core/.env ./.env
export $(echo $(cat .env | sed 's/#.*//g'| xargs) | envsubst);
export $(echo $(cat .env | sed 's/#.*//g'| xargs) | envsubst);
npm install
npm run build
cp -r ./build/* ../../glued-core/public
popd
popd