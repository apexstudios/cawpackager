#!/bin/sh

# I assume you have already configured it correctly
# Means the config.json exists

PACK_OUTPUT=`php packager.php`
FILENAME=`echo "$PACK_OUTPUT" | tail -n 1`

echo $PACK_OUTPUT

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

ARC_INPUT="{ \"id\":\"172\", \"comments\":\"New build had been assembled. \\n Filename is: $FILENAME \\n \\n Link is: \\n $URL \\n \\n Signing off!\" }"

echo ""
echo ""
echo $ARC_INPUT
echo ""
echo ""

echo $ARC_INPUT | arc call-conduit maniphest.update
