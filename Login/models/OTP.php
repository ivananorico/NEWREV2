<?php
class OTP {
    private $conn;
    private $table_name = "user_otp";

    public $id;
    public $user_id;
    public $otp_code;
    public $expires_at;
    public $is_used;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Generate random 6-digit OTP
    public function generateOTP() {
        return sprintf("%06d", mt_rand(1, 999999));
    }

    // Create OTP record
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, otp_code=:otp_code, 
                expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE), is_used=0";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":otp_code", $this->otp_code);

        return $stmt->execute();
    }

    // Verify OTP
    public function verify($user_id, $otp_code) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND otp_code = :otp_code 
                  AND expires_at > NOW() 
                  AND is_used = 0 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":otp_code", $otp_code);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Mark OTP as used
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->markAsUsed($row['id']);
            return true;
        }
        return false;
    }

    // Mark OTP as used
    private function markAsUsed($otp_id) {
        $query = "UPDATE " . $this->table_name . " SET is_used = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $otp_id);
        return $stmt->execute();
    }
}
?>