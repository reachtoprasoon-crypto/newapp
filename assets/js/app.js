// Shared AJAX + UI conventions used across every page in the app.
// Every api/*.php endpoint responds with {success:true, data:...} or
// {success:false, error:"..."} — this wrapper unifies loading state and
// success/error toasts around that envelope, replacing the per-component
// isLoading/useToast boilerplate in the source React app.

const BASE_URL = '/newapp';

function showLoading() {
    $('#loading-overlay').show();
}

function hideLoading() {
    $('#loading-overlay').hide();
}

// Downloads (docx/zip/csv/xlsx/sql) are triggered via a plain navigation, not
// ajaxCall, so there's no response callback to hide the overlay on — the
// browser gives no JS signal when a Content-Disposition: attachment response
// finishes generating server-side. Show the spinner, trigger the download,
// and hide it after delayMs (generous enough to usually outlast generation +
// the browser taking over with its own download UI). This is a timeout
// heuristic, not a true completion signal.
function triggerDownload(url, delayMs) {
    showLoading();
    window.location.href = url;
    setTimeout(hideLoading, delayMs || 6000);
}

function toastSuccess(message) {
    Swal.fire({ icon: 'success', title: message, toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
}

function toastError(message) {
    Swal.fire({ icon: 'error', title: message, toast: true, position: 'top-end', timer: 3500, showConfirmButton: false });
}

// options: { url, data, method ('GET'|'POST'), silent (skip loading overlay),
//           successMessage, isFormData (pass a FormData instance as data, e.g. file uploads) }
function ajaxCall(options) {
    const method = options.method || 'POST';
    if (!options.silent) showLoading();

    const ajaxSettings = {
        url: BASE_URL + options.url,
        method: method,
        data: options.data || {},
        dataType: 'json',
    };
    if (options.isFormData) {
        ajaxSettings.processData = false;
        ajaxSettings.contentType = false;
    }

    return $.ajax(ajaxSettings).then(function (response) {
        if (!options.silent) hideLoading();
        if (!response.success) {
            toastError(response.error || 'Something went wrong.');
            return $.Deferred().reject(response).promise();
        }
        if (options.successMessage) {
            toastSuccess(options.successMessage);
        }
        return response.data;
    }, function (xhr) {
        if (!options.silent) hideLoading();
        const message = (xhr.responseJSON && xhr.responseJSON.error) || 'Request failed. Please try again.';
        toastError(message);
        return $.Deferred().reject(xhr).promise();
    });
}

function confirmDelete(message) {
    return Swal.fire({
        title: 'Are you sure?',
        text: message || 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc3545',
    }).then(function (result) {
        return result.isConfirmed;
    });
}
