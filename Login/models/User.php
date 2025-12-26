<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $middle_name;
    public $suffix;
    public $birthdate;
    public $mobile;
    public $house_number;
    public $street;
    public $barangay;
    public $city;
    public $province;
    public $zip_code;
    public $role;
    public $status;
    public $email_verified;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Check if email exists
    public function emailExists() {
        $query = "SELECT id, first_name, last_name, password_hash, status, email_verified, role 
                  FROM " . $this->table_name . " 
                  WHERE email = :email 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->password_hash = $row['password_hash'];
            $this->status = $row['status'];
            $this->email_verified = $row['email_verified'];
            $this->role = $row['role'];
            return true;
        }
        return false;
    }

    // Get user by ID
    public function getUserById($user_id) {
        $query = "SELECT id, email, first_name, last_name, password_hash, status, email_verified, role 
                  FROM " . $this->table_name . " 
                  WHERE id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->email = $row['email'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->password_hash = $row['password_hash'];
            $this->status = $row['status'];
            $this->email_verified = $row['email_verified'];
            $this->role = $row['role'];
            return true;
        }
        return false;
    }

    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash);
    }

    // Create new user (UPDATED for new address fields)
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET email=:email, 
                    password_hash=:password_hash, 
                    first_name=:first_name, 
                    last_name=:last_name, 
                    middle_name=:middle_name, 
                    suffix=:suffix, 
                    birthdate=:birthdate, 
                    mobile=:mobile, 
                    house_number=:house_number, 
                    street=:street, 
                    barangay=:barangay,
                    city=:city,
                    province=:province,
                    zip_code=:zip_code, 
                    role='user', 
                    status='pending', 
                    email_verified=0";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->middle_name = htmlspecialchars(strip_tags($this->middle_name));
        $this->suffix = htmlspecialchars(strip_tags($this->suffix));
        $this->mobile = htmlspecialchars(strip_tags($this->mobile));
        $this->house_number = htmlspecialchars(strip_tags($this->house_number));
        $this->street = htmlspecialchars(strip_tags($this->street));
        $this->barangay = htmlspecialchars(strip_tags($this->barangay));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->province = htmlspecialchars(strip_tags($this->province));
        $this->zip_code = htmlspecialchars(strip_tags($this->zip_code));

        // Hash password
        $this->password_hash = password_hash($this->password_hash, PASSWORD_DEFAULT);

        // Bind parameters
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":suffix", $this->suffix);
        $stmt->bindParam(":birthdate", $this->birthdate);
        $stmt->bindParam(":mobile", $this->mobile);
        $stmt->bindParam(":house_number", $this->house_number);
        $stmt->bindParam(":street", $this->street);
        $stmt->bindParam(":barangay", $this->barangay);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":province", $this->province);
        $stmt->bindParam(":zip_code", $this->zip_code);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        error_log("User creation failed: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Activate account after OTP verification
    public function activateAccount() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'active', email_verified = 1 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}
?>