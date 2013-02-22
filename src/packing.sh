#!/bin/sh

# I assume you have already configured it correctly
# Means the config.json exists

REPO="https://some.repo/svn/"

PACK_OUTPUT=`php packager.php pack $REPO`
FILENAME=`echo "$PACK_OUTPUT" | tail -n 1`

PUBLISH_OUTPUT=`php publish.php $FILENAME`
URL=`echo "$PUBLISH_OUTPUT" | tail -n 1`
