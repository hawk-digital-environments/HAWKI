<template id="token-list-row-temp">
	<tr id="token-item">
        <th class="index"></th>
        <th class="token-name"></th>
        <th>
            <button class="btn-xs delete-btn-svg" onclick="requestTokenRevoke(this)">
                <x-icon name="trash"/>
            </button>
        </th>
    </tr>
</template>
