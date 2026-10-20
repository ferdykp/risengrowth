@php
    $statusLabels = ['queued' => 'Antrean', 'running' => 'Berjalan', 'completed' => 'Selesai', 'failed' => 'Gagal', 'partial' => 'Sebagian'];
    $statusClasses = ['queued' => 'bg-slate-100 text-slate-600', 'running' => 'bg-blue-50 text-blue-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700', 'partial' => 'bg-amber-50 text-amber-700'];
@endphp


                        @forelse ($runs as $run)
                            @php
                                $runStatus = data_get($run, 'status', 'queued');

                                $statusLabel = $statusLabels[$runStatus] ?? ucfirst($runStatus);

                                $statusClass = $statusClasses[$runStatus] ?? 'bg-slate-100 text-slate-600';
                            @endphp

                            <tr class="border-b border-slate-100">

                                <td class="py-4 pr-4">

                                    <p class="font-semibold text-slate-900">
                                        {{ data_get($run, 'keyword_phrase', '-') }}
                                    </p>

                                    @if(data_get($run, 'status') === 'completed' && (int) data_get($run, 'fetched_count') === 0)
                                        <p class="mt-1 max-w-sm text-xs leading-relaxed text-amber-800">{{ data_get($run, 'source_type') === 'threads' ? 'Threads API mengembalikan 0 postingan. Periksa keyword dan persetujuan akses threads_keyword_search di Meta. Akun terhubung belum membuktikan akses pencarian publik.' : 'Tidak ada kecocokan pada sumber public web / API yang dipindai. Pencarian ini tidak mencakup Threads.' }}</p>
                                    @endif
                                    @if (filled(data_get($run, 'error_summary')))
                                        <p class="mt-1 max-w-xs break-words text-xs leading-relaxed text-red-700">
                                            {{ data_get($run, 'error_summary') }}
                                        </p>
                                    @endif

                                </td>


                                <td class="py-4 pr-4 text-slate-600">
                                    {{ data_get($run, 'source_type') === 'python' ? 'Public web / API' : ucfirst(data_get($run, 'platform', '-')) }}
                                </td>


                                <td class="py-4 pr-4">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>

                                </td>


                                <td class="py-4 pr-4 tabular-nums text-slate-700">
                                    {{ number_format((int) data_get($run, 'fetched_count', 0), 0, ',', '.') }}
                                </td>


                                <td class="py-4 pr-4 tabular-nums text-slate-700">
                                    {{ number_format((int) data_get($run, 'added_count', 0), 0, ',', '.') }}
                                </td>


                                <td class="whitespace-nowrap py-4 text-slate-500">

                                    @if (data_get($run, 'created_at'))
                                        {{ \Illuminate\Support\Carbon::parse(data_get($run, 'created_at'))->format('d M Y, H:i') }}
                                    @else
                                        -
                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="py-16 text-center">

                                    <span class="material-symbols-outlined text-4xl text-slate-300">
                                        history
                                    </span>

                                    <p class="mt-2 font-semibold text-slate-900">
                                        Belum ada riwayat pencarian
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Pilih keyword lalu jalankan pencarian pertama.
                                    </p>

                                </td>

                            </tr>
                        @endforelse

