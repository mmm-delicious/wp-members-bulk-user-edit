<?php
/**
 Plugin Name: WP-Members Bulk User Edit
 Description: Allows for upload of csv to bulk delete users from WP-Members
 * Version: 1.0
 * Author: MMM Delicious
 * Developer: Mark McDonnell
 */

add_action('admin_menu', function() {
    add_users_page(
        'Bulk Edit/Delete Users',
        'Bulk Edit/Delete Users',
        'manage_options',
        'bulk-edit-delete-users',
        'render_bulk_user_admin_page'
    );
});

function render_bulk_user_admin_page() {
    ?>
    <div class="wrap">
        <h1>Bulk Edit/Delete Users</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <?php submit_button('Upload CSV'); ?>
        </form>

        <?php
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv = array_map('str_getcsv', file($_FILES['csv_file']['tmp_name']));
            $headers = array_map('trim', $csv[0]); // Already trims spaces
            $headers = array_map('strtolower', $headers); // Make headers lowercase
            $email_index = array_search('email', $headers);
            $id_index = array_search('id', $headers);

            if ($email_index === false && $id_index === false) {
                echo '<p style="color:red;">CSV must contain an "email" or "ID" column.</p>';
                return;
            }

            echo '<h2>Matched Users</h2><table class="widefat"><thead><tr><th>ID</th><th>Email</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
            foreach (array_slice($csv, 1) as $row) {
                $email = $email_index !== false ? sanitize_email($row[$email_index]) : null;
                $user_id = $id_index !== false ? intval($row[$id_index]) : null;

                $user = false;
                if ($user_id) $user = get_userdata($user_id);
                if (!$user && $email) $user = get_user_by('email', $email);

                if ($user) {
                    $edit_url = get_edit_user_link($user->ID);
                    $delete_url = wp_nonce_url(admin_url("users.php?action=delete&user=$user->ID"), 'bulk-users');
                    echo "<tr>
                        <td>{$user->ID}</td>
                        <td>{$user->user_email}</td>
                        <td>{$user->display_name}</td>
                        <td>
                            <a href='$edit_url'>Edit</a> |
                            <a href='$delete_url' onclick=\"return confirm('Are you sure you want to delete this user?');\">Delete</a>
                        </td>
                    </tr>";
                }
            }
            echo '</tbody></table>';
        }
        ?>
    </div>
    <?php
}
