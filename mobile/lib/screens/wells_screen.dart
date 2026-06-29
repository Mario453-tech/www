import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../i18n/locale_provider.dart';
import '../models/well.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../widgets/well_card.dart';

class WellsScreen extends StatefulWidget {
  const WellsScreen({super.key});

  @override
  State<WellsScreen> createState() => _WellsScreenState();
}

class _WellsScreenState extends State<WellsScreen> {
  List<Well>? _wells;
  bool _loading = true;
  String? _error;
  String _filter = 'all';
  int _loadGeneration = 0;

  static const _filters = [
    ('all', 'wells.filters.all'),
    ('active', 'wells.filters.active'),
    ('paused', 'wells.filters.paused'),
    ('damaged', 'wells.filters.damaged'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    final generation = ++_loadGeneration;
    setState(() {
      _loading = true;
      _error = null;
    });

    final token = context.read<AuthProvider>().token;
    if (token == null) {
      if (mounted && generation == _loadGeneration) {
        setState(() => _loading = false);
      }
      return;
    }

    try {
      final wells = await ApiService.getWells(
        token,
        status: _filter == 'all' ? null : _filter,
      );
      if (mounted && generation == _loadGeneration) {
        setState(() => _wells = wells);
      }
    } on ApiException catch (e) {
      if (mounted && generation == _loadGeneration) {
        setState(() => _error = e.message);
      }
    } catch (_) {
      if (mounted && generation == _loadGeneration) {
        setState(() => _error = 'common.error_connection');
      }
    } finally {
      if (mounted && generation == _loadGeneration) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: _filters.map((entry) {
              final (key, labelKey) = entry;
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: FilterChip(
                  label: Text(context.t(labelKey)),
                  selected: _filter == key,
                  onSelected: (_) {
                    setState(() => _filter = key);
                    _load();
                  },
                ),
              );
            }).toList(),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _buildBody(cs),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(ColorScheme cs) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 48, color: cs.error),
            const SizedBox(height: 8),
            Text(context.resolveText(_error!)),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh),
              label: Text(context.t('common.retry')),
            ),
          ],
        ),
      );
    }

    if (_wells == null || _wells!.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.search_off, size: 48, color: cs.onSurfaceVariant),
            const SizedBox(height: 8),
            Text(
              context.t('wells.empty'),
              style: TextStyle(color: cs.onSurfaceVariant),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      itemCount: _wells!.length,
      padding: const EdgeInsets.only(bottom: 16),
      itemBuilder: (_, index) => WellCard(well: _wells![index]),
    );
  }
}
