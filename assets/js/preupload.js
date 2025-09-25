// Pre-upload script for file/photo/signature fields
// Uploads files to uploads/public_upload.php and stores a temp token in hidden inputs

(function() {
  function $(selector, root) { return (root || document).querySelector(selector); }

  async function preUploadFile(inputEl) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;

    const fieldId = inputEl.id; // e.g., field_<id>
    const fieldName = inputEl.dataset.fieldName; // same as server field name
    const hiddenToken = document.getElementById(fieldId + '_temp');
    const previewContainer = document.getElementById('preview-' + fieldId);
    const previewImg = previewContainer ? previewContainer.querySelector('img.preview-image') : null;

    // Basic client-side size check if data-max-size present (MB)
    const maxMB = parseInt(inputEl.dataset.maxSize || '10', 10);
    if (file.size > maxMB * 1024 * 1024) {
      alert('Selected file exceeds maximum allowed size of ' + maxMB + 'MB');
      inputEl.value = '';
      if (hiddenToken) hiddenToken.value = '';
      return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
      // From forms/view.php to uploads/public_upload.php is ../uploads/public_upload.php
      const resp = await fetch('../uploads/public_upload.php', {
        method: 'POST',
        body: formData
      });
      const json = await resp.json();
      if (!json.success) {
        throw new Error(json.error || 'Upload failed');
      }

      // Store token (use temp_name so server can locate the file reliably)
      if (hiddenToken) hiddenToken.value = json.temp_name || json.temp_id;

      // Show preview if available and image
      if (previewContainer && previewImg && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
          previewImg.src = e.target.result;
          previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      }

      // Clear file input so form submission uses temp token instead
      inputEl.value = '';

    } catch (err) {
      alert('Upload failed: ' + err.message);
      console.error(err);
      if (hiddenToken) hiddenToken.value = '';
    }
  }

  // Attach listeners to all file/photo/signature inputs
  document.addEventListener('change', function(e) {
    if (e.target.matches('input[type="file"][data-preupload="1"]')) {
      preUploadFile(e.target);
    }
  });

  // Optional: allow clearing temp token if user re-selects
  document.addEventListener('click', function(e) {
    if (e.target.matches('.clear-preview-btn')) {
      const fieldId = e.target.dataset.fieldId;
      const hiddenToken = document.getElementById(fieldId + '_temp');
      const previewContainer = document.getElementById('preview-' + fieldId);
      if (hiddenToken) hiddenToken.value = '';
      if (previewContainer) previewContainer.style.display = 'none';
    }
  });

})();
