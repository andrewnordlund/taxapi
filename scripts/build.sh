echo "You're currently working in:"
pwd

cleanUp="true"
echo "************************************"
echo "Starting out, it looks like...."
echo "************************************"
ls -Rl

if [ $# -gt 0 ]; then
  if [ $1 == "false" ]; then
    cleanUp="false"
	echo "Cleanup is false"
  fi 
fi

echo "Removing..."
if [ -d dist ]; then
  rm -Rf dist
  echo "dist"
fi
if [ -d build ]; then
  rm -Rf build
  echo "build"
fi

echo "************************************"
echo "mkdir dist build..."
echo "************************************"
mkdir dist build
#rsync -av --exclude="*.swp" --exclude=".DS_Store" src/ build
echo "************************************"
echo "Moving src to build"
echo "************************************"
if [ -e "$HOME/.gitignore" ]; then
  rsync -ralpcgoDv --exclude-from="$HOME/.gitignore" src/ build
else
  rsync -ralpcgoDv src/ build
#cp -Ra src/* build/
fi
echo "************************************"
echo "Moving build to dist"
echo "************************************"
# delete unnecessary files			
#cp -r build/ dist/
# rsync -rlpcgoDv --exclude-from="$HOME/.gitignore" build/ dist
if [ -e "$HOME/.gitignore" ]; then
  rsync -ralpcgoDv --exclude-from="$HOME/.gitignore" build/ dist
else
#cp -a build/. dist/
  rsync -ralpcgoDv build/ dist
fi
echo "************************************"
echo "And now it looks like...."
echo "************************************"
ls -Ral

if [ "$cleanUp" == "true" ]; then
  rm -Rf build
fi

