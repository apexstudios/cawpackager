#!/bin/sh

# I assume you have already configured it correctly
# Means the config.json exists

REPO="https://some.repo/svn/"
PHAB="https://phabricator.example.com/"

PACK_OUTPUT=`php packager.php pack $REPO`

echo "Packing of "
echo $REPO
echo ""

echo $PACK_OUTPUT

FILENAME=`echo "$PACK_OUTPUT" | tail -n 1`

echo ""
echo "Packed up repo!"
echo ""
echo "Publishing to S3"
echo ""

PUBLISH_OUTPUT=`php publish.php $FILENAME`
URL=`echo "$PUBLISH_OUTPUT" | tail -n 1`

echo $PUBLISH_OUTPUT
echo "Successfully published!"
echo "The URL is"
echo $URL

echo "{ \"id\": \"172\", \"comments\":\"New build had been assembled. \n $URL \n\n Signing off! \" }" | arc call-conduit maniphest.update --conduit-uri=$PHAB
