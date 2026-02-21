const handleAssignUser = async () => {
  if (!selectedUserId) {
    alert('Please select a user');
    return;
  }

  // Validate that selectedUserId is a valid number
  const userIdNum = parseInt(selectedUserId);
  if (isNaN(userIdNum)) {
    alert('Invalid user ID selected');
    return;
  }

  console.log('Assigning user - Permit ID:', id, 'User ID:', userIdNum);

  try {
    const assignUrl = `${API_BASE}/Business/BusinessValidation/assign_user_to_permit.php`;
    const response = await fetch(assignUrl, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        permit_id: id,
        user_id: userIdNum  // Send as number, not string
      })
    });

    const responseText = await response.text();
    console.log('Raw server response:', responseText);
    
    let result;
    try {
      result = JSON.parse(responseText);
    } catch (parseError) {
      console.error('JSON Parse Error:', parseError);
      console.error('Response that failed to parse:', responseText);
      throw new Error('Invalid server response: ' + responseText.substring(0, 100));
    }

    console.log('Parsed response:', result);

    if (result.status === 'success') {
      alert('User assigned successfully!');
      setShowUserSelector(false);
      setAutoMatchedUser(null);
      
      // Update the permit with the new user_id
      setPermit(prev => {
        const updated = {
          ...prev,
          user_id: userIdNum
        };
        console.log('Updated permit state:', updated);
        return updated;
      });
      
      // Keep selectedUserId in sync (as string for radio buttons)
      setSelectedUserId(selectedUserId);
      
    } else {
      alert('Error: ' + (result.message || 'Failed to assign user'));
    }
  } catch (err) {
    console.error('Error assigning user:', err);
    alert('Error assigning user: ' + err.message);
  }
};