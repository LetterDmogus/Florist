<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#fff8fc]">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-8 py-8 bg-white shadow-xl shadow-pink-100/50 overflow-hidden sm:rounded-[2rem] border border-pink-100">
            <slot />
        </div>
    </div>
</template>
