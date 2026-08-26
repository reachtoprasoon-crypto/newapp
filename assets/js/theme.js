(function () {
    let controls = { defaultTheme: null, reportWatermark: null };
    let sourceImage = null; // the raw uploaded Image, recomposited live as sliders move

    function renderPreview() {
        const canvas = document.getElementById('wm_previewCanvas');
        const $noImage = $('#wm_noImage');
        if (!sourceImage) {
            canvas.classList.add('d-none');
            $noImage.removeClass('d-none');
            return;
        }
        canvas.classList.remove('d-none');
        $noImage.addClass('d-none');

        const size = parseInt($('#wm_size').val(), 10);
        const opacity = parseInt($('#wm_opacity').val(), 10) / 100;
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, size, size);
        ctx.globalAlpha = opacity;
        ctx.drawImage(sourceImage, 0, 0, size, size);
        ctx.globalAlpha = 1;
    }

    function setThemeButtons(cval) {
        const isDark = Number(cval) === 1;
        $('#themeBtnLight').toggleClass('btn-primary', !isDark).toggleClass('btn-outline-secondary', isDark);
        $('#themeBtnDark').toggleClass('btn-primary', isDark).toggleClass('btn-outline-secondary', !isDark);
    }

    function loadTheme() {
        ajaxCall({ url: '/api/theme/get.php', method: 'GET', silent: true }).then(function (data) {
            controls = data;
            setThemeButtons(controls.defaultTheme ? controls.defaultTheme.cval : 0);

            if (controls.reportWatermark) {
                const size = Number(controls.reportWatermark.cval) || 350;
                $('#wm_size').val(size);
                $('#wm_sizeLabel').text(size + 'px');
                if (controls.reportWatermark.cdata) {
                    const img = new Image();
                    img.onload = function () { sourceImage = img; renderPreview(); };
                    img.src = controls.reportWatermark.cdata;
                }
            }
        });
    }

    function setTheme(cval) {
        if (!controls.defaultTheme) return;
        ajaxCall({ url: '/api/controls/update.php', data: { conid: controls.defaultTheme.conid, cval: cval }, silent: true })
            .then(function () {
                toastSuccess('Theme updated.');
                setTimeout(function () { window.location.reload(); }, 400);
            });
    }

    $('#themeBtnLight').on('click', function () { setTheme(0); });
    $('#themeBtnDark').on('click', function () { setTheme(1); });

    $('#wm_file').on('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = function () {
            const img = new Image();
            img.onload = function () { sourceImage = img; renderPreview(); };
            img.src = reader.result;
        };
        reader.readAsDataURL(file);
    });

    $('#wm_opacity').on('input', function () {
        $('#wm_opacityLabel').text($(this).val() + '%');
        renderPreview();
    });
    $('#wm_size').on('input', function () {
        $('#wm_sizeLabel').text($(this).val() + 'px');
        renderPreview();
    });

    $('#wm_apply').on('click', function () {
        if (!controls.reportWatermark) {
            toastError('Watermark control not found.');
            return;
        }
        if (!sourceImage) {
            toastError('Choose an image first.');
            return;
        }
        renderPreview();
        const canvas = document.getElementById('wm_previewCanvas');
        const dataUrl = canvas.toDataURL('image/png');
        ajaxCall({
            url: '/api/controls/update.php',
            data: { conid: controls.reportWatermark.conid, cval: $('#wm_size').val(), cdata: dataUrl, allowed: '1' },
            successMessage: 'Watermark updated.',
        });
    });

    loadTheme();
})();
