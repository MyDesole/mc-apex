package ru.mcapex.mod.fabric.mixin;

import net.minecraft.network.chat.Component;
import net.minecraft.world.entity.player.Player;
import org.spongepowered.asm.mixin.Mixin;
import org.spongepowered.asm.mixin.injection.At;
import org.spongepowered.asm.mixin.injection.Inject;
import org.spongepowered.asm.mixin.injection.callback.CallbackInfoReturnable;
import ru.mcapex.mod.fabric.ApexModClient;

/** Значок тира/звания перед ником над головой игрока. */
@Mixin(Player.class)
public abstract class PlayerDisplayNameMixin {
    @Inject(method = "getDisplayName", at = @At("RETURN"), cancellable = true)
    private void apex$prefix(CallbackInfoReturnable<Component> cir) {
        Component prefixed = ApexModClient.withBadge(((Player) (Object) this).getGameProfile().getName(), cir.getReturnValue());
        if (prefixed != null) {
            cir.setReturnValue(prefixed);
        }
    }
}
