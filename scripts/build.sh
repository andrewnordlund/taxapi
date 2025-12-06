echo "You're currently working in:"
pwd

cleanUp="true"

echo "Starting out, it looks like...."
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

echo "mkdir dist build..."
mkdir dist build
#rsync -av --exclude="*.swp" --exclude=".DS_Store" src/ build
echo "Moving src to build"
if [ -e "$HOME/.gitignore" ]; then
  rsync -rlpcgoDv --exclude-from="$HOME/.gitignore" src/ build
else
  rsync -rlpcgoDv src/ build
#cp -Ra src/* build/
fi
echo "Moving build to dist"
# delete unnecessary files			
#cp -r build/ dist/
# rsync -rlpcgoDv --exclude-from="$HOME/.gitignore" build/ dist
if [ -e "$HOME/.gitignore" ]; then
  rsync -rlpcgoDv --exclude-from="$HOME/.gitignore" build/ dist
else
#cp -a build/. dist/
  rsync -rlpcgoDv build/ dist
fi
echo "And now it looks like...."
ls -Rl

if [ "$cleanUp" == "true" ]; then
  rm -Rf build
fi

