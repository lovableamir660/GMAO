import { Ability } from '@casl/ability'

export const ability = new Ability([])

export function updateAbility(permissions) {
  const rules = permissions.map((permission) => {
    const [subject, action] = permission.split(':')
    return { action, subject }
  })
  ability.update(rules)
}

export function resetAbility() {
  ability.update([])
}
