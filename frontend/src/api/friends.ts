import { api } from './client.ts'
import type { FriendsResult } from '../types/friends.ts'

export function listFriends(): Promise<FriendsResult> {
  return api<FriendsResult>('/api/friends')
}

export function sendFriendRequest(username: string): Promise<void> {
  return api<void>('/api/friends/requests', { method: 'POST', body: { username } })
}

export function follow(username: string): Promise<void> {
  return api<void>('/api/friends/following', { method: 'POST', body: { username } })
}

export function unfollow(followId: number): Promise<void> {
  return api<void>(`/api/friends/following/${followId}`, { method: 'DELETE' })
}

export function acceptFriendRequest(requestId: number): Promise<void> {
  return api<void>(`/api/friends/requests/${requestId}/accept`, { method: 'POST' })
}

export function removeFriendRequest(requestId: number): Promise<void> {
  return api<void>(`/api/friends/requests/${requestId}`, { method: 'DELETE' })
}

export function removeFriend(friendshipId: number): Promise<void> {
  return api<void>(`/api/friends/${friendshipId}`, { method: 'DELETE' })
}
