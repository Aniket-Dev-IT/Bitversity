            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Confirm delete actions
            $('.btn-delete').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const itemName = $(this).data('name') || 'this item';
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You want to delete "${itemName}"? This action cannot be undone!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
            
            // Form validation helper
            $('.needs-validation').on('submit', function(e) {
                if (this.checkValidity() === false) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                $(this).addClass('was-validated');
            });
            
            // File upload preview
            $('.file-input').on('change', function() {
                const file = this.files[0];
                const preview = $(this).siblings('.image-preview');
                
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">`);
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.html('<p class="text-muted">No image selected</p>');
                }
            });
            
            // Copy to clipboard functionality
            $('.copy-btn').on('click', function() {
                const text = $(this).data('copy');
                navigator.clipboard.writeText(text).then(() => {
                    $(this).html('<i class="fas fa-check"></i> Copied!');
                    setTimeout(() => {
                        $(this).html('<i class="fas fa-copy"></i> Copy');
                    }, 2000);
                });
            });
            
            // Toggle active status
            $('.toggle-status').on('change', function() {
                const id = $(this).data('id');
                const type = $(this).data('type');
                const status = $(this).is(':checked') ? 1 : 0;
                
                $.ajax({
                    url: '../api/admin/toggle-status.php',
                    method: 'POST',
                    data: {
                        id: id,
                        type: type,
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Failed to update status'
                        });
                    }
                });
            });
            
            // Quick search functionality
            $('#quickSearch').on('keyup', function() {
                const value = $(this).val().toLowerCase();
                $('.searchable-row').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // Bulk actions
            $('#selectAll').on('change', function() {
                $('.item-checkbox').prop('checked', $(this).is(':checked'));
                updateBulkActions();
            });
            
            $('.item-checkbox').on('change', function() {
                updateBulkActions();
            });
            
            function updateBulkActions() {
                const checkedCount = $('.item-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('.bulk-actions').removeClass('d-none');
                    $('.bulk-count').text(checkedCount);
                } else {
                    $('.bulk-actions').addClass('d-none');
                }
            }
            
            // Bulk delete
            $('#bulkDelete').on('click', function() {
                const checkedItems = $('.item-checkbox:checked');
                if (checkedItems.length === 0) return;
                
                Swal.fire({
                    title: 'Delete Selected Items?',
                    text: `You are about to delete ${checkedItems.length} item(s). This action cannot be undone!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete them!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const ids = [];
                        checkedItems.each(function() {
                            ids.push($(this).val());
                        });
                        
                        $.ajax({
                            url: '../api/admin/bulk-delete.php',
                            method: 'POST',
                            data: {
                                ids: ids,
                                type: $(this).data('type')
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Deleted!',
                                        text: response.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error!',
                                        text: response.message
                                    });
                                }
                            }
                        });
                    }
                });
            });
        });
        
        // Function to show loading state
        function showLoading() {
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        }
        
        // Function to hide loading state
        function hideLoading() {
            Swal.close();
        }
        
        // Format numbers
        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }
        
        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>