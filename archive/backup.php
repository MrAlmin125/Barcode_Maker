<?php
// ===== SECURITY =====
$secret_key = "4125";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die("Access Denied");
}

// ===== DATABASE CONFIG =====
$host = "sql110.infinityfree.com";
$user = "if0_40706792";
$pass = "NewTree1";
$db   = "if0_40706792_barcodes";

// ===== DROPBOX TOKEN (CHANGE LATER) =====
$accessToken = "sl.u.AGfm1ZiCn05T-zxJp0Irwf8y5Av3onKebH7tFhDf_uWrPDCNHYMjZR7HFxRwZtgXSCFZQgMP3nSDeGbunoB3_e_amGzeJjc6PikN3wd7tC0vanupa-EkeawraO417gvbEM_ezjWOXy-lMCJke_KbD8BChXcbCXIZ9-QtP5B_4Ign2NSceFmxqTTOKn9-yJbgnoXmzY0WYHUEHhhmP5VARG1iCosgHXs8lCuOWR1LvEBzVNqYuupO-kTJ6oJGtnJWtW-ydlCbE8gWIZ3WidQzb2S62VbqxJGMxw63jABZ04xR552OH9oOiYVTggNKhNr34ubV4L9lHNW2Li8vRfZZ7ZjDqCZjhB_N80wYyiF22bfizhCgp8vGLCjAy6V7TDGzLUuiGkQ9CcI0je_Vam_Mt3g1OgiN_eEbfM8t1S-B6_gX2A3E7jYmS9mLFUU8NaCzzxHiIhO00zpMBPiRgCtGA4LwOEtoDX66DlZdSpRKx_4a4I2NgckQWO_M7GMpM4b4yhsiukGtG5Git8MUaeY8tfUnG05hU1EHp82pTdnc6H5F0Ih-qisnl3E9r6oBIuyZMwy45U7ZcMjOSlN1tzY2igxqgNRIiBYuFMEdyhvc5938Z9TdGxUyYCVhwORtsmXZMe4TVU7M9DqWYTptdGO6oHjhJnfjz1RQsBaleO2sgOe3OINOLX15vssMP2rA63mdXlWWc5Hud3bFVe5bKbHQwOeTQUp5fzQLGhLST99un3F_D0Wm_1p9inPhvsLmzGpvAIZnDfwOraCyzsvHJuzPzA4XxjZ24P5-AWuDGdwF8--zv5WRjRl1rs0rPfNOrLeFoANGF2CRbBT--GhTOM71lZct8spZNn4-lKZMsIG-BrGHJckWmjz2VGDuHQon0VhVnQcinFpxbsX0JLYXRzmYf9Rn1llIC2mL0jnK7Rg7sUcQL0VWr2ouNo1IatzL-xg3J18zpLvbgLAaFQ4NhXdrudHDYQwDo_e0NWHWo0y_JNxJc9J6TrsER2RG_DX_Uhu2h1FJdHwzFWEULFsc3bTOfHubpWPzeS4hhPa9mpcJbuHJIeQNkZJ-ycOkp1STr70axSY8lOV4izuPlqTo6LHKDzAvEGm8QPfq7OheOjOp85lYVG5zIkp4zPKPPDW6z_asKyKOXeqLQuQWp-XUn6mSLX3aTSG5S7WQrG80fAXHO4X3Fz4tctfkidLolytmE5raPzHVFC5cuSKzgf98w3AZxJNbVVpu-U0OPbSaLE1VygnGNLPFoolkHmKHMQbPBQ_ZThkJTTLW561LkuZba2GmUSpA88PoKcLiqhdA1yx6Pcdma7pRv7xU0fze1ZceJdxP-2eXFGMBlgKILkFmrkU_507hrQ2nZ8K32o1L4T6KdyIZLPj18n7V4ogI6ZDX-goEHqk5ZskDZXPVQsHNuKf01kYu";

// ===== CONNECT DB =====
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed");
}
$conn->set_charset("utf8");

// ===== FILE NAME =====
$filename = "backup_" . date("Ymd_His") . ".sql";
$file = fopen($filename, "w");

// ===== TABLES =====
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// ===== EXPORT =====
foreach ($tables as $table) {

    $res = $conn->query("SHOW CREATE TABLE $table");
    $row = $res->fetch_row();
    fwrite($file, "\n\n" . $row[1] . ";\n\n");

    $res = $conn->query("SELECT * FROM $table");
    while ($data = $res->fetch_assoc()) {

        $columns = array_keys($data);

        $values = array_map(function($val) use ($conn) {
            if ($val === null) return "NULL";
            return "'" . $conn->real_escape_string($val) . "'";
        }, array_values($data));

        fwrite($file,
            "INSERT INTO $table (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ");\n"
        );
    }
}

fclose($file);

// ===== LATEST COPY =====
copy($filename, "latest.sql");

// ===== DROPBOX UPLOAD =====
$fp = fopen($filename, "rb");
$size = filesize($filename);

$ch = curl_init("https://content.dropboxapi.com/2/files/upload");

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/octet-stream",
    "Dropbox-API-Arg: " . json_encode([
        "path" => "/backup/" . basename($filename),
        "mode" => "add",
        "autorename" => true
    ])
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, fread($fp, $size));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

fclose($fp);
curl_close($ch);

echo "Backup completed: " . $filename;
?>