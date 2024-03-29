<?php
/*
Plugin Name: Custom Lead Generation
Description: A plugin to manage lead generation, including viewing and adding leads.
Author: Sumit Gupta
Version: 1.0
*/

// Define global variables
global $custom_lead_db_version;
$custom_lead_db_version = '1.0';
add_action('rest_api_init', 'register_custom_lead_routes');

// Plugin activation hook
register_activation_hook(__FILE__, 'custom_lead_generation_install');

// Function to create custom table on activation
function custom_lead_generation_install()
{
    global $wpdb;
    global $custom_lead_db_version;

    $table_name = $wpdb->prefix . 'leads';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        location varchar(100) NOT NULL,
        address varchar(255) NOT NULL,
        status varchar(20) DEFAULT 'Pending',
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('custom_lead_db_version', $custom_lead_db_version);
}

// Add custom menu
add_action('admin_menu', 'custom_lead_generation_menu');

function custom_lead_generation_menu()
{
    add_menu_page('Custom Lead Generation', 'Lead Generation', 'manage_options', 'custom-lead-generation', 'custom_lead_generation_init', 'dashicons-groups');
    add_submenu_page('custom-lead-generation', 'View Leads', 'View Leads', 'manage_options', 'view-leads', 'view_leads_page');
    add_submenu_page('custom-lead-generation', 'Add New Lead', 'Add New Lead', 'manage_options', 'add-new-lead', 'add_new_lead_page');
}

function custom_lead_generation_init()
{
    echo "<div class='wrap'>";
    echo "<h1>Custom Lead Generation Plugin</h1>";
    echo "<p>This plugin allows you to manage lead generation:</p>";
    echo "<ul>";
    echo "<li><a href='admin.php?page=view-leads&status=all'>All Leads</a></li>";
    echo "<li><a href='admin.php?page=view-leads&status=active'>Active Leads</a></li>";
    echo "<li><a href='admin.php?page=view-leads&status=pending'>Pending Leads</a></li>";
    echo "<li><a href='admin.php?page=add-new-lead'>Add New Lead</a></li>";
    echo "</ul>";
    echo "</div>";
}

// View Leads Page
function view_leads_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'leads';

    // Handle bulk action delete
    if (isset($_POST['bulk_delete']) && isset($_POST['lead_ids'])) {
        $ids = implode(',', array_map('absint', $_POST['lead_ids']));
        $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
    }

    // Fetch leads
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Sorting
    $orderby = isset($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'id';
    $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

    // Search
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Filter by status
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

    // Total leads count for pagination
    $total_leads = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR location LIKE '%$search%' OR address LIKE '%$search%') AND (status = '$status_filter' OR '$status_filter' = 'all')");

    // Fetch leads based on status
    if ($status_filter === 'active') {
        $leads = $wpdb->get_results("SELECT * FROM $table_name WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR location LIKE '%$search%' OR address LIKE '%$search%') AND status = 'active' ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A);
    } elseif ($status_filter === 'pending') {
        $leads = $wpdb->get_results("SELECT * FROM $table_name WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR location LIKE '%$search%' OR address LIKE '%$search%') AND status = 'pending' ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A);
    } else {
        $leads = $wpdb->get_results("SELECT * FROM $table_name WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR location LIKE '%$search%' OR address LIKE '%$search%') ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", ARRAY_A);
    }

    // Display Leads
    ?>
    <div class="wrap">
        <h2>View Leads</h2>

        <!-- Search Form -->
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="view-leads">
            <p class="search-box">
                <label class="screen-reader-text" for="lead-search-input">Search Leads:</label>
                <input type="search" id="lead-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="search-submit" class="button" value="Search Leads">
            </p>
        </form>

        <!-- Bulk Delete Form -->
        <form method="post">
            <input type="hidden" name="page" value="view-leads">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="bulk_action">
                        <option value="-1">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <input type="submit" name="bulk_delete" class="button action" value="Apply">
                </div>
                <div class="alignleft actions">
                    <select name="status_filter">
                        <option value="all" <?php selected($status_filter, 'all'); ?>>All Statuses</option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    </select>
                    <input type="submit" name="filter_status" class="button" value="Filter">
                </div>
            </div>

            <!-- Lead Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column"><input type="checkbox"></th>
                        <th><a href="<?php echo admin_url('admin.php?page=view-leads&orderby=name&order=' . ($orderby === 'name' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">Name &#x25B2;&#x25BC;</a></th>
                        <th><a href="<?php echo admin_url('admin.php?page=view-leads&orderby=email&order=' . ($orderby === 'email' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">Email &#x25B2;&#x25BC;</a></th>
                        <th><a href="<?php echo admin_url('admin.php?page=view-leads&orderby=phone&order=' . ($orderby === 'phone' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">Phone &#x25B2;&#x25BC;</a></th>
                        <th><a href="<?php echo admin_url('admin.php?page=view-leads&orderby=location&order=' . ($orderby === 'location' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">Location &#x25B2;&#x25BC;</a></th>
                        <th><a href="<?php echo admin_url('admin.php?page=view-leads&orderby=address&order=' . ($orderby === 'address' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">Address &#x25B2;&#x25BC;</a></th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ($leads as $lead) : ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr($lead['id']); ?>"></th>
                            <td>
                                <?php echo esc_html($lead['name']); ?>
                                <div class="lead-actions">
                                    <a href="<?php echo admin_url('admin.php?page=edit-lead&id=' . $lead['id']); ?>" class="edit">Edit</a> | 
                                    <a href="<?php echo admin_url('admin.php?page=view-leads&delete=' . $lead['id']); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this lead?');">Delete</a>
                                </div>
                            </td>
                            <td><?php echo esc_html($lead['email']); ?></td>
                            <td><?php echo esc_html($lead['phone']); ?></td>
                            <td><?php echo esc_html($lead['location']); ?></td>
                            <td><?php echo esc_html($lead['address']); ?></td>
                            <td><?php echo esc_html($lead['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links(array('total' => ceil($total_leads / $per_page), 'current' => $current_page)); ?>
                </div>
            </div>
        </form>
    </div>
    <style>
    .lead-actions {
        display: none;
    }
    .lead-actions a {
        text-decoration: none;
        color: #0073aa;
        margin: 0 5px;
        padding: 0;
        transition: color 0.3s ease;
    }
    .lead-actions a:hover {
        color: #e91e63;
    }
    td:hover .lead-actions {
        display: inline;
    }
    </style>
    <?php
}



// Add New Lead Page
function add_new_lead_page()
{
    // Check if the form is submitted
    if (isset($_POST['submit_lead'])) {
        // Call function to process form data
        process_lead_form();
    }

    // Display lead form
    ?>
    <div class="wrap">
        <h2>Add New Lead</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="name">Name:</label></th>
                    <td><input type="text" id="name" name="name" required></td>
                </tr>
                <tr>
                    <th><label for="email">Email:</label></th>
                    <td><input type="email" id="email" name="email" required></td>
                </tr>
                <tr>
                    <th><label for="phone">Phone:</label></th>
                    <td><input type="tel" id="phone" name="phone" required></td>
                </tr>
                <tr>
                    <th><label for="location">Location:</label></th>
                    <td><input type="text" id="location" name="location" required></td>
                </tr>
                <tr>
                    <th><label for="address">Address:</label></th>
                    <td><textarea id="address" name="address" required></textarea></td>
                </tr>
                <tr>
                    <th><label for="status">Status:</label></th>
                    <td>
                        <label><input type="radio" name="status" value="active" required> Active</label>
                        <label><input type="radio" name="status" value="pending" required> Pending</label>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="submit_lead" class="button button-primary" value="Submit"></p>
        </form>
    </div>
    <?php
}

// Function to process lead form data
function process_lead_form()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'leads';

    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $location = sanitize_text_field($_POST['location']);
    $address = sanitize_textarea_field($_POST['address']);
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';

    // Insert data into the custom table
    $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'location' => $location,
            'address' => $address,
            'status' => $status,
        )
    );

    echo '<div class="wrap">';
    echo '<p>Lead details submitted successfully!</p>';
    echo '</div>';
}
add_action('rest_api_init', 'register_custom_lead_routes');

function register_custom_lead_routes() {
    register_rest_route('custom-lead/v1', '/leads', array(
        'methods' => 'GET',
        'callback' => 'get_all_leads',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));

    register_rest_route('custom-lead/v1', '/leads/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_single_lead',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));

    register_rest_route('custom-lead/v1', '/leads/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_single_lead',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));

    register_rest_route('custom-lead/v1', '/leads/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_single_lead',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));
}

function get_all_leads($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'leads';
    $leads = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    return rest_ensure_response($leads);
}

function get_single_lead($request) {
    $lead_id = $request->get_param('id');
    global $wpdb;
    $table_name = $wpdb->prefix . 'leads';
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id), ARRAY_A);
    if (!$lead) {
        return new WP_Error('lead_not_found', 'Lead not found', array('status' => 404));
    }
    return rest_ensure_response($lead);
}

function update_single_lead($request) {
    $lead_id = $request->get_param('id');
    $lead_data = $request->get_body();
    // Process and update lead data in the database
    // Example:
    // $wpdb->update($table_name, $lead_data, array('id' => $lead_id));
    return new WP_REST_Response('Lead updated successfully', 200);
}

function delete_single_lead($request) {
    $lead_id = $request->get_param('id');
    global $wpdb;
    $table_name = $wpdb->prefix . 'leads';
    $wpdb->delete($table_name, array('id' => $lead_id));
    return new WP_REST_Response('Lead deleted successfully', 200);
}
?>
