# Friends and followers: problems and expected behavior

This document records the social-connection problems encountered while testing Penderie's friend feature and defines the behavior needed to make it reliable and understandable.

## Problems encountered

1. **A registered account could not be found by its expected username.** The `penderie_test` credentials in the README describe a local test account, but an account created before usernames became mandatory can still have no username stored. Friend lookup uses the username field, not the email address. A verified legacy account can now set its missing username once from **Mon compte**.
2. **The lookup error did not explain the requirements.** “Account not found” did not tell the user that they must enter the other account's username, that usernames appear in **Mon compte**, or that the account must have a verified email.
3. **Ordinary troubleshooting exhausted the friend-request quota.** The original limit was five attempts per account per hour. It was briefly raised to twenty, but the backend consumed an attempt before validating the username or checking whether the request was a duplicate. That fixed limiter has now been removed.
4. **The former relationship model was friend-request-only.** Direct follows and follower lists have now been implemented. A friend request also creates a follow; acceptance makes the accounts friends and creates the reverse follow. Declining or cancelling removes the request but keeps the follow.
5. **The flow requires users to know an exact username.** It has no account search or discovery interface, making it easy to enter the wrong handle.

## Implemented behavior

- Adults can follow and unfollow verified accounts by username.
- The Friends page shows followers and following.
- Sending a friend request creates a follow. Accepting creates the reverse follow and a mutual friend relationship.
- Declining/cancelling a friend request removes the pending request but preserves the follow.
- Repeating a direct follow returns the existing relationship without creating a duplicate.
- The low fixed hourly friend-request limiter has been removed.

## Remaining expected social behavior

Penderie should support one-way follows as well as mutual friendships:

- A person can follow another account directly. The follow takes effect immediately and is visible in follower/following lists; the target does not have to accept the follow.
- Sending a friend request also follows the recipient. While the request is pending, the sender remains their follower.
- If the recipient accepts, the accounts become friends (a mutual relationship). If they do not accept or decline the request, the sender remains a follower. Declining a friend request must not silently unfollow the sender.
- Either person can explicitly unfollow. Blocking is separate: it prevents new follows and requests and removes the relevant existing relationship, with a clear explanation to both parties as appropriate.
- Following or being friends with someone does **not** expose their wardrobe. Inventory visibility still requires a separate, explicit, scoped, revocable share.
- Users can find accounts by username through clear search or discovery. Search results must not reveal email addresses or private account details.
- Use consistent labels and status indicators for **Following**, **Followers**, **Friends**, **Request pending**, **Accept**, **Decline**, **Unfollow**, and **Block**. After an action, update the page to show the actual persisted relationship.

## Request and abuse handling

- Do not impose an arbitrary low hourly quota that prevents ordinary people from connecting with friends. Normal manual follow and friend-request use must not be blocked by a fixed count such as five or twenty per hour.
- Repeated actions for the same pair should be idempotent or return a clear existing-state result; they should not consume a new-request allowance.
- Invalid usernames and accounts that do not exist should produce a useful, non-sensitive explanation and should not consume the allowance for a valid new relationship.
- Preserve proportionate server-side abuse controls for automation, spam, and harassment. Prefer per-target deduplication, block/report controls, and targeted burst protection; do not make normal use look like an unexplained generic failure. If a protective delay is actually applied, show a clear reason and retry time.
- Keep email verification and adult-account requirements for friendship actions as required by the product's safety policy. Do not use a household slug, profile PIN, email address, or unverified account as a substitute for the account identity.

## Acceptance checks

1. Two independently registered, email-verified accounts with usernames can find one another and create a follow; both follower/following views reflect it after reload.
2. A friend request makes the requester a follower immediately. The recipient sees a pending request and can accept or decline it.
3. Accepting makes the relationship mutual and displays both users as friends. Declining leaves the requester following until they explicitly unfollow or are blocked.
4. Repeating a follow or friend request does not create duplicate rows or incorrectly report that the account cannot be found.
5. A valid account is findable with its normalized username regardless of input letter case. Missing usernames on eligible legacy accounts can be claimed once through **Mon compte**; the unique constraint prevents two accounts from claiming the same handle.
6. Typing a wrong username does not exhaust a legitimate user's ability to connect with other accounts.
7. A normal user can follow and send friend requests to multiple people without hitting an arbitrary low hourly cap.
8. Blocking prevents future follows and requests, and neither following nor friendship grants inventory access without a separate share.
9. Tests cover allowed and denied access, pending/accepted/declined/unfollowed/blocked states, duplicate actions, unknown usernames, legacy usernames, refresh persistence, and any abuse protection that remains enabled.

## Current implementation note

The current app supports direct follow/unfollow, follower/following lists, friend requests by exact username, acceptance, cancellation/decline, and removal of accepted friendships. Sending a friend request follows the recipient; accepting creates the reverse follow; declining or cancelling preserves the existing follow. The fixed per-hour friend-request limiter has been removed, and repeated follows are idempotent. Account discovery/search and blocking/reporting are not implemented yet. One-time username setup for verified accounts without a username is available in **Mon compte**. Apply database migrations before testing follows.
