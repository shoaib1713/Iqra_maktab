<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Manage Locations&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process location add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add' || $action === 'edit') {
        // Get form data
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $location_name = isset($_POST['location_name']) ? trim($_POST['location_name']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
        $radius_meters = isset($_POST['radius_meters']) ? intval($_POST['radius_meters']) : 100;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($location_name)) {
            $errors[] = "Location name is required";
        }
        
        if (empty($address)) {
            $errors[] = "Address is required";
        }
        
        if ($latitude === 0 || $longitude === 0) {
            $errors[] = "Valid coordinates are required";
        }
        
        if (empty($errors)) {
            if ($action === 'add') {
                // Insert new location
                $sql = "INSERT INTO office_locations 
                        (location_name, address, latitude, longitude, radius_meters, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddii", $location_name, $address, $latitude, $longitude, $radius_meters, $is_active);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Office location added successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to add office location";
                }
            } else {
                // Update existing location
                $sql = "UPDATE office_locations 
                        SET location_name = ?, 
                            address = ?, 
                            latitude = ?, 
                            longitude = ?, 
                            radius_meters = ?, 
                            is_active = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddiii", $location_name, $address, $latitude, $longitude, $radius_meters, $is_active, $location_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Office location updated successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to update office location";
                }
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
    } elseif ($action === 'delete') {
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        
        // Check if location is used in attendance logs
        $checkSql = "SELECT COUNT(*) as count FROM attendance_logs WHERE punch_in_location_id = ? OR punch_out_location_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $location_id, $location_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $usageCount = $checkResult->fetch_assoc()['count'];
        
        if ($usageCount > 0) {
            $_SESSION['error_message'] = "Cannot delete location as it is used in attendance records. You can deactivate it instead.";
        } else {
            // Delete location
            $sql = "DELETE FROM office_locations WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $location_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Office location deleted successfully";
            } else {
                $_SESSION['error_message'] = "Failed to delete office location";
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: manage_locations.php");
    exit();
}

// Get all locations
$sql = "SELECT * FROM office_locations ORDER BY location_name";
$result = $conn->query($sql);

$locations = [];
while ($location = $result->fetch_assoc()) {
    $locations[] = $location;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Office Locations - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 5px;
        }
        .location-card {
            transition: all 0.3s ease;
        }
        .location-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-map-marker-alt me-2"></i> Manage Office Locations</h5>
                        <p class="text-muted">Add, edit, or remove office locations for attendance tracking.</p>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Map and Add Location Form -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-map me-2"></i> Office Locations Map
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="map"></div>
                                <div class="mt-3">
                                    <p class="text-muted small">
                                        <i class="fas fa-info-circle me-1"></i> Click on the map to set coordinates for a new location.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-plus-circle me-2"></i> Add New Location
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="locationForm" method="POST" action="">
                                    <input type="hidden" name="action" id="formAction" value="add">
                                    <input type="hidden" name="location_id" id="location_id" value="0">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Location Name</label>
                                        <input type="text" class="form-control" name="location_name" id="location_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" id="address" rows="2" required></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Latitude</label>
                                            <input type="text" class="form-control" name="latitude" id="latitude" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Longitude</label>
                                            <input type="text" class="form-control" name="longitude" id="longitude" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Radius (meters)</label>
                                        <input type="number" class="form-control" name="radius_meters" id="radius_meters" min="50" max="1000" value="100" required>
                                        <small class="text-muted">Geofencing radius for attendance check-in</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active" checked>
                                        <label class="form-check-label" for="is_active">Active Location</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" id="submitBtn" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Location
                                        </button>
                                    </div>
                                    
                                    <button type="button" id="resetBtn" class="btn btn-link text-secondary d-none">
                                        <i class="fas fa-undo me-1"></i> Cancel Edit
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Locations List -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-list me-2"></i> Office Locations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="locationsTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Address</th>
                                                <th>Coordinates</th>
                                                <th>Radius</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($locations as $location): ?>
                                            <tr>
                                                <td><?php echo $location['location_name']; ?></td>
                                                <td><?php echo $location['address']; ?></td>
                                                <td>
                                                    <small>
                                                        Lat: <?php echo $location['latitude']; ?><br>
                                                        Lng: <?php echo $location['longitude']; ?>
                                                    </small>
                                                </td>
                                                <td><?php echo $location['radius_meters']; ?> m</td>
                                                <td>
                                                    <?php if ($location['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                                                            data-location='<?php echo json_encode($location); ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $location['id']; ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $location['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Location</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete <strong><?php echo $location['location_name']; ?></strong>?</p>
                                                                <p class="text-danger">
                                                                    <i class="fas fa-exclamation-triangle me-1"></i> 
                                                                    This action cannot be undone if there are no attendance records using this location. 
                                                                    Otherwise, you will be asked to deactivate it instead.
                                                                </p>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="location_id" value="<?php echo $location['id']; ?>">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Delete Location</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap" async defer></script>
    <script>
        let map;
        let markers = [];
        let circles = [];
        
        // Initialize map
        function initMap() {
            // Default center (Mumbai)
            const center = { lat: 19.0760, lng: 72.8777 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                center: center,
                zoom: 12
            });
            
            // Add existing locations to map
            const locations = <?php echo json_encode($locations); ?>;
            
            locations.forEach(location => {
                addMarkerToMap(
                    parseFloat(location.latitude), 
                    parseFloat(location.longitude),
                    location.location_name,
                    parseInt(location.radius_meters),
                    location.is_active == 1
                );
            });
            
            // Click on map to set coordinates
            map.addListener('click', function(event) {
                document.getElementById('latitude').value = event.latLng.lat().toFixed(8);
                document.getElementById('longitude').value = event.latLng.lng().toFixed(8);
            });
        }
        
        // Add marker to map
        function addMarkerToMap(lat, lng, title, radius, isActive) {
            const position = { lat: lat, lng: lng };
            
            // Create marker
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: title,
                animation: google.maps.Animation.DROP,
                opacity: isActive ? 1.0 : 0.5
            });
            
            markers.push(marker);
            
            // Create circle for geofencing radius
            const circle = new google.maps.Circle({
                map: map,
                center: position,
                radius: radius,
                fillColor: isActive ? '#4285F4' : '#999999',
                fillOpacity: 0.2,
                strokeColor: isActive ? '#4285F4' : '#999999',
                strokeOpacity: 0.8,
                strokeWeight: 1
            });
            
            circles.push(circle);
            
            // Add info window
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="width: 200px;">
                        <h6 style="margin-bottom: 5px;">${title}</h6>
                        <p style="margin-bottom: 5px;">Radius: ${radius}m</p>
                        <p style="margin-bottom: 0;">Status: ${isActive ? 'Active' : 'Inactive'}</p>
                    </div>
                `
            });
            
            marker.addListener('click', function() {
                infoWindow.open(map, marker);
            });
        }
        
        // Clear all markers and circles
        function clearMapElements() {
            markers.forEach(marker => marker.setMap(null));
            circles.forEach(circle => circle.setMap(null));
            markers = [];
            circles = [];
        }
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#locationsTable').DataTable({
                "pageLength": 10,
                "order": [[ 0, "asc" ]]
            });
            
            // Edit location
            $('.edit-btn').click(function() {
                const locationData = $(this).data('location');
                
                // Populate form
                $('#formAction').val('edit');
                $('#location_id').val(locationData.id);
                $('#location_name').val(locationData.location_name);
                $('#address').val(locationData.address);
                $('#latitude').val(locationData.latitude);
                $('#longitude').val(locationData.longitude);
                $('#radius_meters').val(locationData.radius_meters);
                $('#is_active').prop('checked', locationData.is_active == 1);
                
                // Update UI
                $('#submitBtn').html('<i class="fas fa-save me-2"></i> Update Location');
                $('#resetBtn').removeClass('d-none');
                
                // Scroll to form
                $('html, body').animate({
                    scrollTop: $("#locationForm").offset().top - 100
                }, 200);
            });
            
            // Reset form
            $('#resetBtn').click(function() {
                $('#formAction').val('add');
                $('#location_id').val(0);
                $('#locationForm').trigger('reset');
                $('#submitBtn').html('<i class="fas fa-save me-2"></i> Save Location');
                $(this).addClass('d-none');
            });
        });
    </script>
    <script>
        function deleteLocation(locationId) {
            if (confirm('Are you sure you want to delete this location?')) {
                const locationData = <?php echo json_encode($locations); ?>[locationId - 1];
                const locationName = locationData.location_name;
                
                // Send delete request to server
                fetch('manage_locations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        location_id: locationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Location deleted successfully');
                        // Remove from table
                        const table = $('#locationsTable').DataTable();
                        table.row(locationId - 1).remove().draw();
                    } else {
                        alert('Failed to delete location');
                    }
                });
            }
        }
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html> 