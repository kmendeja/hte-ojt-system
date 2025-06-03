$(document).ready(function() {
    // Load initial data
    loadSectionAssignments();
    loadCoordinators();

    // Handle program selection change
    $('#program, #edit_program').on('change', function() {
        const formType = $(this).attr('id').startsWith('edit_') ? 'edit_' : '';
        updateSectionDropdown(formType);
    });

    // Handle academic year selection change
    $('#academic_year, #edit_academic_year').on('change', function() {
        const formType = $(this).attr('id').startsWith('edit_') ? 'edit_' : '';
        updateSectionDropdown(formType);
    });

    // Handle form submission for adding
    $('#addSectionForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add');

        $.ajax({
            url: 'php/admin_assignsec.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message || 'Section assigned successfully!'
                    }).then(() => {
                        $('#addSectionModal').modal('hide');
                        $('#addSectionForm')[0].reset();
                        loadSectionAssignments();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to assign section'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing your request'
                });
            }
        });
    });

    // Handle edit confirmation button click
    $(document).on('click', '#editConfirmBtn', function() {
        const id = $('#edit_id').val();
        const coordinator_id = $('#edit_coordinator_id').val();
        const academic_year = $('#edit_academic_year').val();
        const program = $('#edit_program').val();
        const section = $('#edit_section').val();

        // Validate inputs
        if (!coordinator_id || !academic_year || !program || !section) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please fill in all fields'
            });
            return;
        }

        // Send AJAX request
        $.ajax({
            url: 'php/admin_assignsec.php',
            type: 'POST',
            data: {
                action: 'edit',
                id: id,
                coordinator_id: coordinator_id,
                academic_year: academic_year,
                program: program,
                section: section
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message || 'Section assignment updated successfully!'
                    }).then(() => {
                        $('#editSectionModal').modal('hide');
                        loadSectionAssignments();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to update section assignment'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing your request'
                });
            }
        });
    });

    // Handle delete
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'php/admin_assignsec.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Deleted!',
                                response.message || 'Section assignment has been deleted.',
                                'success'
                            );
                            loadSectionAssignments();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to delete section assignment'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while processing your request'
                        });
                    }
                });
            }
        });
    });
});

// Load section assignments
function loadSectionAssignments() {
    $.ajax({
        url: 'php/admin_assignsec.php',
        type: 'GET',
        data: { action: 'list' },
        success: function(response) {
            if (response.success) {
                const assignments = response.assignments;
                let html = '';
                assignments.forEach(function(assignment, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${assignment.full_name}</td>
                            <td>${assignment.academic_year}</td>
                            <td>${assignment.program}</td>
                            <td>${assignment.section}</td>
                            <td>
                                <button class="btn btn-primary btn-sm edit-btn" 
                                        onclick="editSection(${assignment.id})"
                                        data-id="${assignment.id}" 
                                        data-coordinator="${assignment.coordinator_id}"
                                        data-program="${assignment.program}"
                                        data-section="${assignment.section}"
                                        data-academic-year="${assignment.academic_year}">
                                    Edit
                                </button>
                                <button class="btn btn-danger btn-sm delete-btn" 
                                        data-id="${assignment.id}">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#assignmentTableBody').html(html);
            }
        }
    });
}

// Load coordinators for dropdown
function loadCoordinators() {
    $.ajax({
        url: 'php/get_coordinators.php',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                const coordinators = response.coordinators;
                let html = '<option value="">Select Coordinator</option>';
                coordinators.forEach(function(coordinator) {
                    html += `<option value="${coordinator.coordinator_id}">${coordinator.full_name}</option>`;
                });
                $('#coordinator_id, #edit_coordinator_id').html(html);
            }
        }
    });
}

// Update section dropdown based on program and academic year selection
function updateSectionDropdown(formType = '') {
    const program = $(`#${formType}program`).val();
    const academic_year = $(`#${formType}academic_year`).val();
    const currentSection = $(`#${formType}section`).val(); // Store current selection

    if (!program || !academic_year) {
        $(`#${formType}section`).html('<option value="">Select Section</option>');
        return;
    }

    // Show loading state
    $(`#${formType}section`).html('<option value="">Loading sections...</option>');

    $.ajax({
        url: 'php/get_available_sections.php',
        type: 'GET',
        data: {
            program: program,
            academic_year: academic_year
        },
        success: function(response) {
            if (response.success) {
                const sections = response.sections;
                let options = '<option value="">Select Section</option>';
                sections.forEach(section => {
                    const selected = section === currentSection ? 'selected' : '';
                    options += `<option value="${section}" ${selected}>${section}</option>`;
                });
                $(`#${formType}section`).html(options);

                // If this is an edit form and we have a current section but it's not in the list
                if (formType === 'edit_' && currentSection && !sections.includes(currentSection)) {
                    options += `<option value="${currentSection}" selected>${currentSection}</option>`;
                    $(`#${formType}section`).html(options);
                }
            } else {
                $(`#${formType}section`).html('<option value="">No sections available</option>');
            }
        },
        error: function() {
            $(`#${formType}section`).html('<option value="">Error loading sections</option>');
        }
    });
}

// Handle edit button click
function editSection(id) {
    const btn = $(`.edit-btn[data-id="${id}"]`);
    const coordinator = btn.data('coordinator');
    const program = btn.data('program');
    const section = btn.data('section');
    const academicYear = btn.data('academic-year');

    // Set initial values
    $('#edit_id').val(id);
    $('#edit_coordinator_id').val(coordinator);
    $('#edit_program').val(program);
    $('#edit_academic_year').val(academicYear);
    $('#edit_section').val(section); // Set initial section value

    // First show the modal
    $('#editSectionModal').modal('show');

    // Then update the sections dropdown
    updateSectionDropdown('edit_');
} 