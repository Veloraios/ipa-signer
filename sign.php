
<?php
function uploadToGitHub($filePath, $repo, $token) {
    $url = "https://uploads.github.com/repos/$repo/releases/latest/assets?name=" . basename($filePath);
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: token $token",
        "Content-Type: application/zip" // Change this if you're uploading a different file type
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents($filePath));
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response);
}

function cleanupUploads() {
    $files = glob('uploads/*'); // Get all file names
    foreach ($files as $file) {
        // Check if the file was last modified more than an hour ago
        if (is_file($file) && (time() - filemtime($file) > 3600)) {
            unlink($file); // Delete the file
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ipaFile = $_FILES['ipaFile']['tmp_name'];
    $p12File = $_FILES['p12File']['tmp_name'];
    $p12Password = $_POST['p12Password'];
    $provisioningProfile = $_POST['provisioningProfile'];

    // Generate unique names for uploaded files
    $timestamp = time();
    $ipaFileName = "uploads/app_$timestamp.ipa";
    $p12FileName = "uploads/certificate_$timestamp.p12";
    $outputPath = "uploads/signed_app_$timestamp.ipa";

    // Ensure the IPA file is uploaded
    if (move_uploaded_file($ipaFile, $ipaFileName) && move_uploaded_file($p12File, $p12FileName)) {
        $ipaPath = $ipaFileName;
        $p12Path = $p12FileName;

        // Import the .p12 file into the keychain
        $importCommand = "security import $p12Path -P $p12Password -T /usr/bin/codesign";
        shell_exec($importCommand);

        // Use codesign to sign the IPA
        $codesignCommand = "codesign -f -s 'iPhone Distribution: Your Company Name' --entitlements entitlements.plist $ipaPath";
        $output = shell_exec($codesignCommand);

        // Package the IPA with the provisioning profile
        $packageCommand = "xcrun -sdk iphoneos PackageApplication -v $ipaPath -o $outputPath --sign 'iPhone Distribution: Your Company Name' --embed '$provisioningProfile'";
        shell_exec($packageCommand);

        // Check if the signing was successful
        if ($output) {
            // Upload to GitHub
            $token = 'your_github_token'; // Replace with your token
            $repo = 'yourusername/repo'; // Replace with your repo
            $uploadResponse = uploadToGitHub($outputPath, $repo, $token);

            if (isset($uploadResponse->id)) {
                echo "Signing successful! Uploaded to GitHub Releases.";
            } else {
                echo "Signing successful, but failed to upload to GitHub.";
            }
        } else {
            echo "Signing failed. Check the console for errors.";
        }

        // Clean up uploads directory
        cleanupUploads();
    } else {
        echo "Failed to upload files.";
    }
} else {
    echo "Invalid request method.";
}
?>
