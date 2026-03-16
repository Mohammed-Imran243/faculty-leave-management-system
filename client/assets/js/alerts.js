// SweetAlert2 Wrapper System
// Custom styled to match the CAHCET Faculty Leave Management System theme

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        confirmButtonColor: '#10b981',
        background: '#ffffff',
        backdrop: `rgba(0,0,0,0.4)`,
        customClass: {
            popup: 'swal2-custom-glass'
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        confirmButtonColor: '#ef4444',
        background: '#ffffff',
        backdrop: `rgba(0,0,0,0.4)`,
        customClass: {
            popup: 'swal2-custom-glass'
        }
    });
}

function showWarning(message) {
    Swal.fire({
        icon: 'warning',
        title: 'Warning',
        text: message,
        confirmButtonColor: '#f59e0b',
        background: '#ffffff',
        backdrop: `rgba(0,0,0,0.4)`,
    });
}

function showConfirm(message, confirmCallback) {
    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, proceed!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            confirmCallback();
        }
    });
}

function showLoading(message = 'Processing...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function closeLoading() {
    Swal.close();
}
