<div class="add-members-section row flex-col ">
    <div class="added-members-list"></div>

    <label>{{ $translation["MemberInvite"] }}</label>

    <div class="search-panel">
        <div class="search-input-wrapper">

            <div class="search-input">
                <input 
                    class="input-styleless" 
                    list="searchResults" 
                    name="user-search-bar" 
                    id="user-search-bar" 
                    placeholder="{{ $translation["PH_SearchBar"] }}" 
                    oninput="searchUser(this)"
                    autocomplete="off"
                    onkeypress="onHandleKeydownUserSearch(event, this)">

                    
                <select class="user-role-selector" id="user-role-selector">
                    <option value="admin">Admin</option>
                    <option value="editor">Editor</option>
                    <option value="viewer">Viewer</option>
                </select>
            </div>
 

            <ul class="custom-datalist" id="searchResults">
            <!-- Suggestions will be dynamically inserted here -->
            </ul>

        </div>
        
        <button class="btn-md-stroke" onclick="onAddUserButton(this)">{{ $translation["Add"] }}</button>
    </div>

</div>
