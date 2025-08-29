<div class="modal"  id="data-protection">
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                {{-- POLICY CONTENT DYNAMICALLY LOADS HERE --}}
                <div id="policy-content"></div>

                <div class="modal-buttons-bar">
                    <button id="declineBtn" class="btn-lg-stroke align-end">{{ $translation["Cancel"] }}</button>
                    <button id="confirmBtn" class="btn-lg-fill align-end" onclick="modalClick(this)" >{{ $translation["Confirm"] }}</button>
                </div>

                <br>
                <br>
            </div>
        </div>
	</div>
</div>

<script>
window.addEventListener('DOMContentLoaded', async () => {
    // Assume fetchLatestPolicy() returns an object {view, announcement}
    const {view, announcement} = await fetchLatestPolicy();

    // Render the HTML (MD rendered to HTML string)
    const renderedHtml = md.render(view, false);

    const parent = document.querySelector('#data-protection')
    // Insert the rendered HTML into the designated container
    const policyContentBox = parent.querySelector('#policy-content');
    policyContentBox.innerHTML = renderedHtml;
    // Add target and rel attributes for external links (XSS-protection best practice)
    policyContentBox.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute("rel", "noopener noreferrer");
    });

    parent.querySelector('#declineBtn').addEventListener('click', () => {
        forceLogoutUser();
    });
});
</script>
