#! /bin/bash
set -e

echo
echo "Deploy airwallex-online-payments-gateway WordPress Plugin"
echo

# Set up some default values. Feel free to change these in your own script
CURRENTDIR=$(pwd)
PLUGINSLUG="airwallex-online-payments-gateway"
PLUGINDIR="$CURRENTDIR/release/$PLUGINSLUG"
SVNPATH="/tmp/SVN/$PLUGINSLUG"
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG"
MAINFILE="$PLUGINSLUG.php"
SVNUSER="airwallex"

echo
echo "Slug: $PLUGINSLUG"
echo "Plugin directory: $PLUGINDIR"
echo "Main file: $MAINFILE"
echo "Temp checkout path: $SVNPATH"
echo "Remote SVN repo: $SVNURL"
echo "SVN username: $SVNUSER"
echo

# Check directory exists.
if [ ! -d "$PLUGINDIR" ]; then
	echo "Directory $PLUGINDIR not found. Aborting."
	exit 1
fi

# Check main plugin file exists.
if [ ! -f "$PLUGINDIR/$MAINFILE" ]; then
	echo "Plugin file $PLUGINDIR/$MAINFILE not found. Aborting."
	exit 1
fi

echo "Checking version in main plugin file matches version in readme.txt file..."
echo

# Check version in readme.txt is the same as plugin file after translating both to Unix line breaks to work around grep's failure to identify Mac line breaks
PLUGINVERSION=$(grep "AIRWALLEX_VERSION" $PLUGINDIR/$MAINFILE | awk -F"'" '{print $4}' | tr -d '\r')
echo "$MAINFILE version: $PLUGINVERSION"
PLUGINHEADERVERSION=$(grep -i "Version:" $PLUGINDIR/$MAINFILE | awk -F' ' '{print $NF}' | tr -d '\r')
echo "$MAINFILE header version: $PLUGINHEADERVERSION"
READMEVERSION=$(grep -i "Stable tag:" $PLUGINDIR/readme.txt | awk -F' ' '{print $NF}' | tr -d '\r')
echo "readme.txt version: $READMEVERSION"

if [ "$PLUGINVERSION" != "$PLUGINHEADERVERSION" ]; then
	echo "Version in $MAINFILE header and constant don't match, Exiting..."
    exit 1;
elif [ "$PLUGINVERSION" != "$READMEVERSION" ]; then
	echo "Version in readme.txt & $MAINFILE don't match. Exiting...."
	exit 1
fi

# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy WordPress plugin"
echo
echo ".........................................."
echo

echo

echo "Changing to $PLUGINDIR"
cd $PLUGINDIR

# if git show-ref --tags --quiet --verify -- "refs/tags/$PLUGINVERSION"
if git show-ref --tags --quiet --verify -- "refs/tags/$PLUGINVERSION"; then
	echo "Git tag $PLUGINVERSION does exist. Let's continue..."
else
	echo "$PLUGINVERSION does not exist as a git tag. Aborting."
	exit 1
fi

echo

echo
echo "Clear $SVNPATH"
rm -rf $SVNPATH/

echo "Creating local copy of SVN repo trunk..."
svn checkout $SVNURL $SVNPATH --depth immediates
svn update --quiet $SVNPATH/trunk --set-depth infinity
svn update --quiet $SVNPATH/tags/$PLUGINVERSION --set-depth infinity

echo "Ignoring GitHub specific files"
svn propset svn:ignore "README.md
Thumbs.db
.git
.gitignore" "$SVNPATH/trunk/"

echo "Copying plugin files to the trunk of SVN"
rsync $PLUGINDIR/* -ri --del -m --exclude ".*" $SVNPATH/trunk/ | grep sT

echo

echo "Changing directory to SVN and committing to trunk."
cd $SVNPATH/trunk/
# Delete all files that should not now be added.
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2"@"}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2"@"}' | xargs svn add
svn commit --username=$SVNUSER -m "new updates and bug fixes. See the version log"

# echo

echo "Creating new SVN tag and committing it"
cd $SVNPATH
svn update --quiet $SVNPATH/tags/$PLUGINVERSION
svn copy --quiet trunk tags/$PLUGINVERSION
# Remove assets and trunk directories from tag directory
# svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/trunk
cd $SVNPATH/tags/$PLUGINVERSION
svn commit --username=$SVNUSER -m "Tagging version $PLUGINVERSION"

echo

echo "Removing temporary directory $SVNPATH."
cd $SVNPATH
cd ..
rm -fr $SVNPATH/

echo "*** FIN ***"