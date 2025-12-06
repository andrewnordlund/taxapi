echo "You're currently working in:"
pwd

cleanUp="true"

if [ $# -gt 0 ]; then
  if [ $1 == "false" ]; then
    cleanUp="false"
	echo "Cleanup is false"
  fi 
fi

if [ -d dist ]; then
  rm -Rf dist
fi
if [ -d build ]; then
  rm -Rf build
fi

mkdir dist build
#rsync -av --exclude="*.swp" --exclude=".DS_Store" src/ build
if [ -e "$HOME/.gitignore" ]; then
  rsync -rlpcgoDv --exclude-from="$HOME/.gitignore" src/ build
  else
  cp -R src/* build/
fi
# delete unnecessary files			
cp -r build/ dist/
ls -Rl

if [ "$cleanUp" == "true" ]; then
  rm -Rf build
fi

