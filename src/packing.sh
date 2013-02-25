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

PUBLISH_OUTPUT=`php publish.php "$FILENAME"`
URL=`echo "$PUBLISH_OUTPUT" | tail -n 1`

echo $PUBLISH_OUTPUT
echo "Successfully published!"
echo "The URL is"
echo $URL

rm $FILENAME && echo "Deleted $FILENAME" ? echo "Could not delete $FILENAME"

php post.php "$FILENAME"
