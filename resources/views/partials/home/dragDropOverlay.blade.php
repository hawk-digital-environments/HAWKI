<div class="drag-drop-overlay">
  <div class="drag-drop-box">
    <div class="drag-drop-content">
        <div class="icon">
            <img src="/img/upload.png" alt="Upload">
        </div>
        <div class="drag-drop-text">
            <h4>{{ __('Upload_Overlay_Title') }}</h4>
            <p class="drag-drop-desc">{{ __('Upload_Overlay_Desc') }}</p>
            <p class="drag-status-msg" aria-live="polite"></p>
        </div>
    </div>
      <div class="drag-drop-status" aria-live="polite">
          <div class="drag-status-valid">
              <x-icon name="check"/>
              <span>{{ __('Upload_Overlay_Valid') }}</span>
          </div>
          <div class="drag-status-invalid">
              <x-icon name="x"/>
              <span>{{ __('Upload_Overlay_Invalid') }}</span>
          </div>
      </div>
  </div>
</div>
