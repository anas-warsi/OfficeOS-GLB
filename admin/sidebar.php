<?php
require_once('../config/database.php');

// $sql = "SELECT id, username, email full_name, role, status  FROM users ";

$sql = "SELECT * FROM users ";

$result = $conn->query($sql);

$all_users = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $all_users[] = $row; 
    }
}

?>

<style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .bottom-area{
            height: 90vh;
            display: flex;
            
        }

        .left-side{
            width: 20%;
            background-color: #b465fe;

        }

        .right-side{
            width: 80%;
            background-color: #d8b1fc;
        }
        td{
            text-align:center;
            padding:5px;
            
        }
        
    </style>

    <div class="bottom-area">
        <div class="left-side"></div>
        <div class="right-side">
            <table border="2" width="100%" >
                <thead >
                    <tr>
                      <th>Id</th>
                      <th>Username</th>
                      <th>Email</th>
                      <th>full_name</th>
                      <th>role</th>
                      <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_users as $user): ?>
                    <tr>
                        <td><?php echo $user['id'] ?></td>
                        <td><?php echo htmlspecialchars($user['username']) ?></td>
                        <td><?php echo htmlspecialchars($user['email']) ?></td>
                        <td><?php echo $user['full_name'] ?></td>
                        <td><?php echo $user['role'] ?></td>
                        <td><?php echo $user['status'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>