export interface FriendUser {
  id: number
  username: string | null
  firstName: string
  displayName?: string
  photoUrl?: string | null
}

export interface FriendEntry {
  id: number
  user: FriendUser
}

export interface FriendsResult {
  friends: FriendEntry[]
  incoming: FriendEntry[]
  outgoing: FriendEntry[]
  followers: FriendEntry[]
  following: FriendEntry[]
}
