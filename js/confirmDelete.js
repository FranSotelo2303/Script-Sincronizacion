function confirmDelete(fileId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "La eliminación será PERMANENTE.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete.php?id=' + fileId;
        }
    });
}
