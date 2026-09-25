
<style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .header{
            height: 10vh;
            width: 100%;
            background-color: blueviolet;
            display: flex;
            justify-content: space-between;
        }
        .col{
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 0px 10px;
                color: white;
        }
        .admin-heading{
            font-weight: bold;
            font-family: Arial, Helvetica, sans-serif;
            padding-left: 40px;
        }
        .logout{
            color: red;
            text-decoration:none;
            font-weight:bold;
            font-size:15px;
            font-family: 'Poppins', sans-serif;
            border:2px solid red;
            padding: 4px 4px;
            border-radius:4px;
        }
    </style>
        <div class="header">
        <div class="col admin-heading">Admin Dashboard</div>
        <div class="col">Welcome (Admin)</div>
        <div class="col"><a class="logout" href="../logout.php">Logout</a></div>
    </div>