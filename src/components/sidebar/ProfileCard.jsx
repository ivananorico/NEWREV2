import React from 'react'

function ProfileCard({ name, role, avatarUrl }) {
  return (
    <div className="p-4 flex items-center space-x-3">
      <img 
        src={avatarUrl} 
        alt="Profile" 
        className="w-10 h-10 rounded-full object-cover"
      />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate dark:text-white">
          {name}
        </p>
        <p className="text-xs text-gray-500 truncate dark:text-gray-400">
          {role}
        </p>
      </div>
    </div>
  )
}

export default ProfileCard