<script>
    (function () {
        var iconInput = document.getElementById('icon');
        var colorInput = document.getElementById('color');
        var titleInput = document.getElementById('title');
        var descInput = document.getElementById('description');
        var previewIcon = document.getElementById('cabinet-mp-preview-icon');
        var previewTitle = document.getElementById('cabinet-mp-preview-title');
        var previewDesc = document.getElementById('cabinet-mp-preview-desc');

        function updatePreview() {
            if (previewIcon && iconInput) {
                previewIcon.innerHTML = iconInput.value || '<i class="bi bi-grid"></i>';
            }
            if (previewIcon && colorInput) {
                previewIcon.style.background = colorInput.value || '#0d6efd';
            }
            if (previewTitle && titleInput) {
                previewTitle.textContent = titleInput.value || @json(__('Title'));
            }
            if (previewDesc && descInput) {
                previewDesc.textContent = descInput.value || @json(__('Description'));
            }
        }

        ['input', 'change'].forEach(function (evt) {
            [iconInput, colorInput, titleInput, descInput].forEach(function (el) {
                if (el) {
                    el.addEventListener(evt, updatePreview);
                }
            });
        });

        updatePreview();
    })();
</script>
