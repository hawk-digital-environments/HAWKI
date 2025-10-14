#!/bin/bash
# This allows the final image to hook in it's own script files
if [ -f ${BASH_SOURCE%/*}/boot.local.sh ]; then
	source ${BASH_SOURCE%/*}/boot.local.sh;
fi

bash -c "${*}"
