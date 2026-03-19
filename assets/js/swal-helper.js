/**
 * ============================================
 * SweetAlert2 Helper - Karaoke Show
 * ============================================
 * 
 * Funções utilitárias para mensagens bonitas
 * Inclua após o SweetAlert2 CDN
 */

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

// Funções de mensagem rápida
const showSuccess = (message, title = 'Sucesso!') => {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonColor: '#8b5cf6'
    });
};

const showError = (message, title = 'Erro!') => {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonColor: '#ef4444'
    });
};

const showWarning = (message, title = 'Atenção!') => {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        confirmButtonColor: '#f59e0b'
    });
};

const showInfo = (message, title = 'Info') => {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        confirmButtonColor: '#3b82f6'
    });
};

// Toast rápido
const toastSuccess = (message) => Toast.fire({ icon: 'success', title: message });
const toastError = (message) => Toast.fire({ icon: 'error', title: message });
const toastInfo = (message) => Toast.fire({ icon: 'info', title: message });

// Confirmação
const showConfirm = async (message, title = 'Tem certeza?', confirmText = 'Sim', cancelText = 'Cancelar') => {
    const result = await Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: '#8b5cf6',
        cancelButtonColor: '#6b7280'
    });
    return result.isConfirmed;
};

// Confirmação de exclusão (mais perigosa)
const showDeleteConfirm = async (itemName = 'este item') => {
    const result = await Swal.fire({
        title: 'Excluir?',
        text: `Tem certeza que deseja excluir ${itemName}? Esta ação não pode ser desfeita!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280'
    });
    return result.isConfirmed;
};

// Loading
const showLoading = (message = 'Carregando...') => {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
};

const hideLoading = () => Swal.close();
